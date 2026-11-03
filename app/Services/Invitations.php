<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StoreInvitation;
use App\Models\StoreMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class Invitations
{
    /**
     * Call while holding the store row lock.
     *
     * @param  array<string, mixed>  $data
     */
    public function issue(Store $store, User $admin, array $data): string
    {
        StoreAccess::check(! in_array($store->status, ['suspended', 'archived']), 'Reactivate the store before inviting an owner.');
        $email = mb_strtolower(trim($data['email']));
        $user = User::where('email', $email)->first();
        $new = ! $user;
        if ($user) {
            StoreAccess::check(! empty($data['existing_identity']), 'This email already has an account. Confirm that you intend to invite the existing identity.');
            StoreAccess::check($user->status === 'active', 'This account is disabled.');
        } else {
            $user = User::create(['name' => $data['name'], 'email' => $email, 'password' => Str::random(64)]);
        }
        // A never-activated identity still needs to choose its first password on a resend.
        $new = $new || (! $user->email_verified_at && StoreInvitation::where('user_id', $user->id)->where('new_identity', true)->whereNull('accepted_at')->exists());
        StoreInvitation::where('store_id', $store->id)->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
        StoreMembership::where('store_id', $store->id)->where('status', 'pending')->update(['status' => 'revoked', 'revoked_at' => now()]);
        $current = StoreMembership::where('store_id', $store->id)->where('user_id', $user->id)->where('status', 'active')->exists();
        StoreAccess::check(! $current, 'This person is already the active owner.');
        StoreMembership::create(['store_id' => $store->id, 'user_id' => $user->id, 'role' => 'owner', 'status' => 'pending']);
        $token = Str::random(64);
        $invite = StoreInvitation::create(['store_id' => $store->id, 'user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'new_identity' => $new, 'expires_at' => now()->addHours(72)]);
        StoreAccess::audit($admin, $store->id, 'owner.invited', $invite->id, ['email' => $email]);

        $url = route('invitation.show', $token);
        app(EmailOutbox::class)->invitation($invite, $store, $user, $url);

        return $url;
    }

    public function find(string $token): StoreInvitation
    {
        return StoreInvitation::where('token_hash', hash('sha256', $token))->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->firstOrFail();
    }

    public function accept(string $token, ?User $signedIn, ?string $password): User
    {
        $invitation = $this->find($token);

        return DB::transaction(function () use ($invitation, $token, $signedIn, $password): User {
            $store = Store::whereKey($invitation->store_id)->lockForUpdate()->firstOrFail();
            $invite = $this->find($token);
            abort_unless(in_array($store->status, ['pending_activation', 'active']), 403);
            $user = User::whereKey($invite->user_id)->lockForUpdate()->firstOrFail();
            abort_unless($user->status === 'active', 403);
            abort_unless($signedIn === null || $signedIn->id === $user->id, 403, 'Sign out of the other account before accepting this invitation.');
            if (! $invite->new_identity || $user->email_verified_at) {
                abort_unless($signedIn?->id === $user->id, 403, 'Sign in with the invited account first.');
            } else {
                StoreAccess::check($password !== null, 'Choose your password.');
                $user->forceFill(['password' => $password, 'email_verified_at' => now()])->save();
            }
            if (! $user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
            StoreMembership::where('store_id', $store->id)->where('status', 'active')->update(['status' => 'revoked', 'revoked_at' => now()]);
            StoreMembership::where('store_id', $store->id)->where('user_id', $user->id)->where('status', 'pending')->update(['status' => 'active', 'activated_at' => now()]);
            $store->update(['status' => 'active']);
            $invite->update(['accepted_at' => now()]);
            StoreAccess::audit($user, $store->id, 'owner.activated', $invite->id);

            return $user;
        }, 3);
    }
}
