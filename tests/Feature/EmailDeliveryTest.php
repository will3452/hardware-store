<?php

namespace Tests\Feature;

use App\Mail\TransactionalEmail;
use App\Models\Announcement;
use App\Models\EmailDelivery;
use App\Models\Store;
use App\Models\StoreInvitation;
use App\Models\User;
use App\Services\EmailOutbox;
use App\Services\Invitations;
use App\Support\Tenancy\StoreContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Livewire\Livewire;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class EmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private EmailOutbox $outbox;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'smtp']);
        Mail::fake();
        $this->outbox = app(EmailOutbox::class);
    }

    private function invitation(): EmailDelivery
    {
        $store = Store::factory()->create(['status' => 'pending_activation']);
        DB::transaction(fn () => app(Invitations::class)->issue($store, User::factory()->create(), ['name' => 'Owner', 'email' => 'owner@example.com']));

        return EmailDelivery::where('kind', 'invitation')->firstOrFail();
    }

    public function test_invitation_is_transactional_encrypted_and_delivered_once_without_rotating_the_link(): void
    {
        DB::beginTransaction();
        $this->invitation();
        Mail::assertNothingSent();
        DB::rollBack();
        $this->assertDatabaseCount('email_deliveries', 0);
        $this->assertDatabaseCount('store_invitations', 0);
        $delivery = $this->invitation();
        $url = $delivery->payload['action_url'];
        $this->assertStringNotContainsString($url, DB::table('email_deliveries')->value('payload'));
        $this->assertArrayNotHasKey('payload', $delivery->toArray());
        $this->outbox->drain();
        $this->outbox->drain();
        Mail::assertSent(TransactionalEmail::class, fn ($mail) => $mail->messageData['action_url'] === $url && $mail->hasTo('owner@example.com'));
        Mail::assertSentCount(1);
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->payload);
        $this->assertDatabaseCount('store_invitations', 1);
    }

    public function test_smtp_failure_retries_same_message_and_redacts_provider_error(): void
    {
        $delivery = $this->invitation();
        $url = $delivery->payload['action_url'];
        $manager = new MailManager($this->app);
        Mail::shouldReceive('to')->once()->andThrow(new TransportException('AUTH password=secret-token recipient=owner@example.com'));
        $this->outbox->drain();
        $delivery->refresh();
        $this->assertSame('pending', $delivery->status);
        $this->assertSame('smtp_delivery_failed', $delivery->last_error);
        $this->assertSame($url, $delivery->payload['action_url']);
        $this->assertTrue($delivery->available_at->isFuture());
        Mail::swap($manager);
        Mail::fake();
        $this->outbox->drain();
        Mail::assertNothingSent();
        $this->travel(61)->seconds();
        $this->outbox->drain();
        Mail::assertSentCount(1);
        $this->assertSame(2, $delivery->fresh()->attempts);
    }

    public function test_resend_cancels_old_invitation_and_suspended_store_blocks_delivery(): void
    {
        $first = $this->invitation();
        DB::transaction(fn () => app(Invitations::class)->issue(Store::findOrFail($first->store_id), User::factory()->create(), ['name' => 'Owner', 'email' => $first->recipient, 'existing_identity' => true]));
        $this->outbox->drain();
        $this->assertSame('cancelled', $first->fresh()->status);
        Mail::assertSentCount(1);
        $second = EmailDelivery::where('status', 'sent')->firstOrFail();
        $this->assertNotSame($first->source_id, $second->source_id);
        $other = Store::factory()->create(['status' => 'pending_activation']);
        DB::transaction(fn () => app(Invitations::class)->issue($other, User::factory()->create(), ['name' => 'Other', 'email' => 'other@example.com']));
        $other->update(['status' => 'suspended']);
        $this->outbox->drain();
        Mail::assertSentCount(1);
        $this->assertDatabaseHas('email_deliveries', ['store_id' => $other->id, 'status' => 'cancelled', 'payload' => null]);
    }

    public function test_password_recovery_queues_valid_links_and_cancels_consumed_or_disabled_accounts(): void
    {
        $user = User::factory()->create();
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasNoErrors();
        $delivery = EmailDelivery::where('kind', 'password_reset')->firstOrFail();
        $token = $delivery->payload['token'];
        $this->assertTrue(Password::broker()->tokenExists($user, $token));
        $this->post(route('password.update'), ['email' => $user->email, 'token' => $token, 'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertSessionHasNoErrors();
        $this->outbox->drain();
        $this->assertSame('cancelled', $delivery->fresh()->status);
        Mail::assertSent(TransactionalEmail::class, fn ($mail) => $mail->messageData['title'] === 'Account security update');
        $user->forceFill(['status' => 'disabled'])->save();
        $user->sendPasswordResetNotification(Password::broker()->createToken($user));
        $this->assertSame(1, EmailDelivery::where('kind', 'password_reset')->count());
    }

    public function test_verification_is_rate_limited_and_the_emailed_link_verifies_the_user(): void
    {
        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();
        $user->sendEmailVerificationNotification();
        $this->assertDatabaseCount('email_deliveries', 1);
        $delivery = EmailDelivery::firstOrFail();
        $url = $delivery->payload['action_url'];
        $this->outbox->drain();
        $this->actingAs($user)->get($url)->assertRedirect();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Mail::assertSentCount(1);
    }

    public function test_email_change_notifies_old_address_and_verifies_new_address(): void
    {
        $user = User::factory()->create();
        $old = $user->email;
        $this->actingAs($user);
        Livewire::test('pages::settings.profile')->set('email', 'new@example.com')->call('updateProfileInformation')->assertHasNoErrors();
        $this->outbox->drain();
        Mail::assertSent(TransactionalEmail::class, fn ($mail) => $mail->hasTo($old) && $mail->messageData['title'] === 'Account security update');
        Mail::assertSent(TransactionalEmail::class, fn ($mail) => $mail->hasTo('new@example.com') && $mail->messageData['title'] === 'Verify your email address');
    }

    public function test_announcements_require_opt_in_snapshot_the_audience_and_recheck_membership(): void
    {
        $owner = User::factory()->create();
        $store = Store::factory()->create(['status' => 'active']);
        $membership = $owner->memberships()->create(['store_id' => $store->id, 'role' => 'owner', 'status' => 'active']);
        $otherOwner = User::factory()->create();
        $otherOwner->memberships()->create(['store_id' => Store::factory()->create(['status' => 'active'])->id, 'role' => 'owner', 'status' => 'active']);
        $data = ['title' => 'Maintenance', 'body' => 'Scheduled maintenance.', 'user_id' => $owner->id, 'publish_at' => now()->subMinute()];
        Announcement::create($data);
        $item = Announcement::create($data + ['email_owners' => true, 'store_ids' => [$store->id]]);
        $this->outbox->prepareAnnouncements();
        $this->outbox->prepareAnnouncements();
        $this->assertDatabaseCount('email_deliveries', 1);
        $this->assertSame($owner->email, EmailDelivery::firstOrFail()->recipient);
        $membership->update(['status' => 'revoked']);
        $this->outbox->drain();
        Mail::assertNothingSent();
        $this->assertSame('cancelled', EmailDelivery::firstOrFail()->status);
        $this->assertNotNull($item->fresh()->email_prepared_at);
    }

    public function test_withdrawn_scheduled_and_expired_announcements_are_not_sent(): void
    {
        $owner = User::factory()->create();
        $owner->memberships()->create(['store_id' => Store::factory()->create(['status' => 'active'])->id, 'role' => 'owner', 'status' => 'active']);
        $data = ['title' => 'Notice', 'body' => 'Message', 'user_id' => $owner->id, 'email_owners' => true];
        $item = Announcement::create($data + ['publish_at' => now()->addHour()]);
        $this->outbox->drain();
        $this->assertDatabaseCount('email_deliveries', 0);
        $this->travel(61)->minutes();
        $this->outbox->prepareAnnouncements();
        $item->update(['withdrawn_at' => now()]);
        $this->outbox->drain();
        Mail::assertNothingSent();
        $this->assertSame('cancelled', EmailDelivery::firstOrFail()->status);
    }

    public function test_stale_lease_is_recovered_and_expired_failure_payload_is_erased(): void
    {
        $delivery = $this->invitation();
        $delivery->update(['status' => 'sending', 'lease_until' => now()->subMinute(), 'attempts' => 1]);
        app(StoreContext::class)->set(Store::factory()->create());
        $this->outbox->drain();
        Mail::assertSentCount(1);
        $this->assertSame(2, $delivery->fresh()->attempts);
        try {
            app(StoreContext::class)->id();
            $this->fail('Store context leaked.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
        $delivery->update(['status' => 'failed', 'payload' => ['token' => 'expired-secret'], 'expires_at' => now()->subMinute()]);
        $this->outbox->drain();
        $this->assertNull($delivery->fresh()->payload);
        $this->assertSame('cancelled', $delivery->fresh()->status);
    }

    public function test_non_smtp_transport_and_overlapping_drain_do_not_mark_messages_sent(): void
    {
        $delivery = $this->invitation();
        config(['mail.default' => 'log']);
        $this->outbox->drain();
        $this->assertSame('pending', $delivery->fresh()->status);
        config(['mail.default' => 'smtp']);
        $lock = Cache::lock('email-outbox-drain', 300);
        $lock->get();
        $this->assertSame(0, $this->outbox->drain());
        $lock->release();
        Mail::assertNothingSent();
    }

    public function test_admin_can_review_and_retry_failures_but_never_see_tokens(): void
    {
        $delivery = $this->invitation();
        $url = $delivery->payload['action_url'];
        $delivery->update(['status' => 'failed', 'attempts' => 5]);
        $owner = User::factory()->create();
        $this->actingAs($owner)->get(route('admin.email'))->assertForbidden();
        $admin = User::factory()->withTwoFactor()->create();
        $admin->platformPermissions()->create(['permission' => 'platform.stores.manage']);
        $admin->platformPermissions()->create(['permission' => 'platform.portal.access']);
        $this->actingAs($admin)->withSession(['platform.mfa' => ['user_id' => $admin->id, 'fingerprint' => hash('sha256', $admin->two_factor_secret)]]);
        $this->get(route('admin.email'))->assertOk()->assertSee('owner@example.com')->assertDontSee($url);
        $this->post(route('admin.email.retry', $delivery->id))->assertSessionHasNoErrors();
        $this->outbox->drain();
        $this->assertSame(6, $delivery->fresh()->attempts);
        Mail::assertSentCount(1);
        $this->post(route('admin.email.retry', $delivery->id))->assertSessionHasErrors();
        $this->post(route('admin.email.test'), ['recipient' => 'attacker@example.com'])->assertSessionHasNoErrors();
        $this->outbox->drain();
        Mail::assertSent(TransactionalEmail::class, fn ($mail) => $mail->hasTo($admin->email));
    }

    public function test_exhausted_delivery_stops_until_explicit_retry_and_never_recreates_source(): void
    {
        $delivery = $this->invitation();
        Mail::shouldReceive('to')->times(5)->andThrow(new TransportException('Provider unavailable'));
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->outbox->drain();
            $this->travel(31)->minutes();
        }
        $this->outbox->drain();
        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertSame(5, $delivery->fresh()->attempts);
        $this->assertDatabaseCount('store_invitations', 1);
        $this->assertDatabaseCount('stores', 1);
    }

    public function test_published_announcement_sends_once_and_future_stores_are_not_backfilled(): void
    {
        $owner = User::factory()->create();
        $owner->memberships()->create(['store_id' => Store::factory()->create(['status' => 'active'])->id, 'role' => 'owner', 'status' => 'active']);
        $item = Announcement::create(['title' => 'Notice', 'body' => 'Message', 'user_id' => $owner->id, 'email_owners' => true, 'publish_at' => now()->subMinute()]);
        $this->outbox->drain();
        Mail::assertSent(TransactionalEmail::class, fn ($mail) => $mail->hasTo($owner->email) && $mail->messageData['title'] === 'Notice');
        $nextOwner = User::factory()->create();
        $nextOwner->memberships()->create(['store_id' => Store::factory()->create(['status' => 'active'])->id, 'role' => 'owner', 'status' => 'active']);
        $item->update(['publish_at' => now()->subSecond()]);
        $this->outbox->drain();
        Mail::assertSentCount(1);
    }

    public function test_changed_recipient_and_consumed_invitation_cancel_pending_delivery(): void
    {
        $delivery = $this->invitation();
        User::findOrFail($delivery->user_id)->update(['email' => 'changed@example.com']);
        $this->outbox->drain();
        Mail::assertNothingSent();
        $this->assertSame('cancelled', $delivery->fresh()->status);
        $delivery->refresh()->update(['status' => 'pending', 'payload' => ['title' => 'Test', 'body' => 'Test'], 'recipient' => 'changed@example.com']);
        StoreInvitation::whereKey($delivery->source_id)->update(['accepted_at' => now()]);
        $this->outbox->drain();
        Mail::assertNothingSent();
        $this->assertNull($delivery->fresh()->payload);
    }

    public function test_security_events_queue_notices_without_two_factor_secrets(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        TwoFactorAuthenticationConfirmed::dispatch($user);
        TwoFactorAuthenticationDisabled::dispatch($user);
        $this->outbox->drain();
        Mail::assertSentCount(2);
        Mail::assertSent(TransactionalEmail::class, fn ($mail) => str_contains($mail->messageData['body'], 'enabled') && ! str_contains(json_encode($mail->messageData), $user->two_factor_secret));
    }

    public function test_message_has_branded_html_plain_text_and_stable_message_id(): void
    {
        $delivery = $this->invitation();
        $mail = new TransactionalEmail($delivery->id, $delivery->payload);
        $mail->assertSeeInHtml('Pyesio');
        $mail->assertSeeInText('Accept invitation');
        $this->assertSame($mail->headers()->messageId, (new TransactionalEmail($delivery->id, $delivery->payload))->headers()->messageId);
        $this->assertStringNotContainsString('token_hash', $mail->render());
    }
}
