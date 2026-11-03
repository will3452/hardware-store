<?php

namespace App\Services;

use App\Mail\TransactionalEmail;
use App\Models\Announcement;
use App\Models\EmailDelivery;
use App\Models\Store;
use App\Models\StoreInvitation;
use App\Models\StoreMembership;
use App\Models\User;
use App\Support\Tenancy\StoreContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

final class EmailOutbox
{
    public function configured(): bool
    {
        // A logging/failover transport must never be presented as SMTP acceptance.
        return config('mail.default') === 'smtp';
    }

    /** @param array<string, string> $payload */
    public function enqueue(string $kind, string $recipient, array $payload, ?User $user = null, ?string $storeId = null, ?string $sourceId = null, ?string $dedupe = null, ?CarbonInterface $expires = null): EmailDelivery
    {
        return EmailDelivery::firstOrCreate(['dedupe_key' => $dedupe ?? (string) Str::ulid()], [
            'kind' => $kind, 'recipient' => $recipient, 'payload' => $payload,
            'user_id' => $user?->id, 'store_id' => $storeId, 'source_id' => $sourceId,
            'status' => 'pending', 'available_at' => now(), 'expires_at' => $expires ?? now()->addDays(3),
        ]);
    }

    public function invitation(StoreInvitation $invite, Store $store, User $user, string $url): void
    {
        $this->enqueue('invitation', $user->email, [
            'title' => 'Your store invitation',
            'body' => 'You have been invited to manage '.$store->display_name.' on Pyesio. Open the invitation to review and accept access.',
            'action_url' => $url, 'action_text' => 'Accept invitation',
            'note' => 'This invitation expires in 72 hours. Existing account holders must sign in. If you were not expecting it, contact your platform administrator.',
        ], $user, $store->id, $invite->id, 'invitation:'.$invite->id, $invite->expires_at);
    }

    public function passwordReset(User $user, string $token): void
    {
        $this->enqueue('password_reset', $user->email, [
            'title' => 'Reset your password', 'body' => 'We received a password reset request for your Pyesio account.',
            'action_url' => route('password.reset', ['token' => $token, 'email' => $user->email]),
            'action_text' => 'Reset password', 'token' => $token,
            'note' => 'This link expires in 60 minutes. If you did not request a reset, ignore this email; your password remains unchanged.',
        ], user: $user, dedupe: 'reset:'.hash('sha256', $user->id.$token), expires: now()->addMinutes(60));
    }

    public function securityNotice(User $user, string $body, ?string $recipient = null): void
    {
        $this->enqueue('security_notice', $recipient ?? $user->email, [
            'title' => 'Account security update', 'body' => $body,
            'note' => 'If you did not make this change, contact your platform administrator immediately. Never share your password or two-factor recovery codes.',
        ], user: $user);
    }

    /** Snapshot active owners once, when an explicitly opted-in announcement becomes published. */
    public function prepareAnnouncements(): void
    {
        $ids = Announcement::where('email_owners', true)->whereNull('email_prepared_at')->whereNull('withdrawn_at')
            ->where('publish_at', '<=', now())->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->limit(10)->pluck('id');
        foreach ($ids as $id) {
            DB::transaction(function () use ($id): void {
                $announcement = Announcement::whereKey($id)->lockForUpdate()->firstOrFail();
                if ($announcement->email_prepared_at || ! $this->published($announcement)) {
                    return;
                }
                $memberships = StoreMembership::where('status', 'active')->where('role', 'owner');
                if ($announcement->store_ids !== null) {
                    $memberships->whereIn('store_id', $announcement->store_ids);
                }
                foreach ($memberships->lazyById(100) as $membership) {
                    $user = User::find($membership->user_id);
                    $store = Store::find($membership->store_id);
                    if (! $user || $user->status !== 'active' || $store?->status !== 'active') {
                        continue;
                    }
                    $this->enqueue('announcement', $user->email, [
                        'title' => $announcement->title, 'body' => $announcement->body,
                        'action_url' => route('store.dashboard', $store->id), 'action_text' => 'Open store portal',
                        'note' => 'Service announcement for '.$store->display_name.'.',
                    ], $user, $store->id, $announcement->id, 'announcement:'.$announcement->id.':'.$store->id.':'.$user->id, $announcement->expires_at ?? now()->addDays(3));
                }
                $announcement->update(['email_prepared_at' => now()]);
            }, 3);
        }
    }

    private function published(Announcement $announcement): bool
    {
        return $announcement->publish_at !== null && $announcement->publish_at->isPast()
            && $announcement->withdrawn_at === null && (! $announcement->expires_at || $announcement->expires_at->isFuture());
    }

    public function eligible(EmailDelivery $delivery): bool
    {
        if ($delivery->expires_at->isPast() || ! $delivery->payload) {
            return false;
        }
        if ($delivery->kind === 'test') {
            return true;
        }
        $user = User::find($delivery->user_id);
        if (! $user || $user->status !== 'active') {
            return false;
        }
        // Security alerts can intentionally go to the previous email address.
        if ($delivery->kind === 'security_notice') {
            return true;
        }
        if ($user->email !== $delivery->recipient) {
            return false;
        }
        if ($delivery->kind === 'password_reset') {
            return Password::broker()->tokenExists($user, $delivery->payload['token'] ?? '');
        }
        if ($delivery->kind === 'verification') {
            return ! $user->hasVerifiedEmail();
        }
        $store = Store::find($delivery->store_id);
        if (! $store || ! in_array($store->status, ['active', 'pending_activation'])) {
            return false;
        }
        if ($delivery->kind === 'invitation') {
            return StoreInvitation::whereKey($delivery->source_id)->where('store_id', $store->id)->where('user_id', $user->id)
                ->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->exists();
        }
        if ($delivery->kind === 'announcement') {
            $announcement = Announcement::find($delivery->source_id);

            return $store->status === 'active' && $announcement !== null && $announcement->email_owners && $this->published($announcement)
                && ($announcement->store_ids === null || in_array($store->id, $announcement->store_ids, true))
                && StoreMembership::where('store_id', $store->id)->where('user_id', $user->id)->where('status', 'active')->where('role', 'owner')->exists();
        }

        return false;
    }

    public function retry(EmailDelivery $delivery, User $admin): void
    {
        DB::transaction(function () use ($delivery, $admin): void {
            $locked = EmailDelivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            StoreAccess::check($locked->status === 'failed' && $this->eligible($locked), 'This email cannot be retried. Request a new account link if it has expired.');
            // Keep the lifetime attempt count; a manual retry grants one additional attempt.
            $locked->update(['status' => 'pending', 'available_at' => now(), 'lease_until' => null, 'last_error' => null]);
            StoreAccess::audit($admin, $locked->store_id, 'email.retry_requested', $locked->id);
        });
    }

    public function drain(int $limit = 10, int $seconds = 35): int
    {
        $context = app(StoreContext::class);
        $context->clear();
        $lock = Cache::lock('email-outbox-drain', 300);
        if (! $lock->get()) {
            return 0;
        }
        try {
            $started = microtime(true);
            $this->prepareAnnouncements();
            // Expired sensitive payloads are removed, including exhausted failures.
            EmailDelivery::where('expires_at', '<=', now())->whereNotNull('payload')
                ->where(fn ($q) => $q->where('status', '!=', 'sending')->orWhere('lease_until', '<=', now()))
                ->update(['status' => 'cancelled', 'payload' => null, 'lease_until' => null, 'last_error' => 'expired']);
            if (! $this->configured()) {
                return 0;
            }
            $processed = 0;
            while ($processed < $limit && microtime(true) - $started < $seconds) {
                $delivery = DB::transaction(function (): ?EmailDelivery {
                    $row = EmailDelivery::where(fn ($q) => $q->where(fn ($q) => $q->where('status', 'pending')->where('available_at', '<=', now()))
                        ->orWhere(fn ($q) => $q->where('status', 'sending')->where('lease_until', '<=', now())))
                        ->orderBy('expires_at')->orderBy('id')->lockForUpdate()->first();
                    if ($row) {
                        $row->update(['status' => 'sending', 'attempts' => $row->attempts + 1, 'lease_until' => now()->addMinutes(5)]);
                    }

                    return $row;
                });
                if (! $delivery) {
                    break;
                }
                $this->deliver($delivery);
                $processed++;
            }

            return $processed;
        } finally {
            $context->clear();
            $lock->release();
        }
    }

    private function deliver(EmailDelivery $delivery): void
    {
        $context = app(StoreContext::class);
        $context->clear();
        try {
            if (! $this->eligible($delivery)) {
                $delivery->update(['status' => 'cancelled', 'payload' => null, 'lease_until' => null, 'last_error' => 'no_longer_eligible']);

                return;
            }
            if ($delivery->store_id) {
                $context->set(Store::findOrFail($delivery->store_id));
            }
            Mail::to($delivery->recipient)->send(new TransactionalEmail($delivery->id, $delivery->payload ?? []));
            $delivery->update(['status' => 'sent', 'sent_at' => now(), 'payload' => null, 'lease_until' => null, 'last_error' => null]);
        } catch (Throwable $exception) {
            // SMTP exceptions may contain addresses, credentials, or message content. Never persist their text.
            $error = $exception instanceof TransportExceptionInterface ? 'smtp_delivery_failed' : 'message_processing_failed';
            $delay = [60, 300, 900, 1800][min($delivery->attempts - 1, 3)];
            $delivery->update(['status' => $delivery->attempts >= 5 ? 'failed' : 'pending', 'available_at' => now()->addSeconds($delay), 'lease_until' => null, 'last_error' => $error]);
        } finally {
            $context->clear();
        }
    }
}
