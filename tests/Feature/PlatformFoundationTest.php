<?php

namespace Tests\Feature;

use App\Jobs\Middleware\UseStoreContext;
use App\Models\Store;
use App\Models\StoreMembership;
use App\Models\StoreModule;
use App\Models\User;
use App\Support\Tenancy\StoreContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function owner(Store $store): User
    {
        $user = User::factory()->create();
        $user->memberships()->create([
            'store_id' => $store->id, 'role' => 'owner', 'status' => 'active',
        ]);

        return $user;
    }

    public function test_public_registration_is_unavailable(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
    }

    public function test_regular_user_cannot_open_admin_portal(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_admin_must_enroll_in_mfa(): void
    {
        $admin = User::factory()->create();
        $admin->platformPermissions()->create(['permission' => 'platform.portal.access']);

        $this->actingAs($admin)->get('/admin')->assertRedirect(route('security.edit'));
    }

    public function test_mfa_enrollment_alone_does_not_authorize_an_existing_session(): void
    {
        $admin = User::factory()->withTwoFactor()->create();
        $admin->platformPermissions()->create(['permission' => 'platform.portal.access']);

        $this->actingAs($admin)->get('/admin')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_admin_with_session_mfa_proof_can_open_portal(): void
    {
        $admin = User::factory()->withTwoFactor()->create();
        $admin->platformPermissions()->create(['permission' => 'platform.portal.access']);

        $this->actingAs($admin)->withSession(['platform.mfa' => [
            'user_id' => $admin->id,
            'fingerprint' => hash('sha256', $admin->two_factor_secret),
        ]])->get('/admin')->assertOk()->assertSee('Admin Portal');
    }

    public function test_two_factor_login_establishes_admin_session_proof(): void
    {
        $admin = User::factory()->withTwoFactor()->create();
        $admin->platformPermissions()->create(['permission' => 'platform.portal.access']);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.login'));
        $this->post('/two-factor-challenge', ['recovery_code' => 'recovery-code-1'])
            ->assertRedirect(route('dashboard', absolute: false))
            ->assertSessionHas('platform.mfa.user_id', $admin->id);
        $this->get('/admin')->assertOk();
    }

    public function test_bootstrap_admin_uses_prompted_password_and_refuses_second_bootstrap(): void
    {
        $password = 'Foundation-Only-482!';
        $this->artisan('app:create-admin')
            ->expectsQuestion('Administrator name', 'Platform Operator')
            ->expectsQuestion('Administrator email', 'operator@example.test')
            ->expectsQuestion('Password', $password)
            ->expectsQuestion('Confirm password', $password)
            ->assertSuccessful();

        $admin = User::where('email', 'operator@example.test')->firstOrFail();
        $this->assertTrue(Hash::check($password, $admin->password));
        $this->assertTrue($admin->hasPlatformPermission('platform.portal.access'));
        $this->assertTrue($admin->hasVerifiedEmail());
        $this->artisan('app:create-admin')->assertFailed();
    }

    public function test_disabled_user_cannot_sign_in(): void
    {
        $user = User::factory()->create(['status' => 'disabled']);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_disabled_identity_loses_existing_session_access(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'disabled']))
            ->get('/dashboard')->assertForbidden();
        $this->assertGuest();
    }

    public function test_store_owner_sees_only_their_store_and_cannot_select_another(): void
    {
        $a = Store::factory()->create(['display_name' => 'Alpha Hardware']);
        $b = Store::factory()->create(['display_name' => 'Beta Hardware']);
        $owner = $this->owner($a);
        $this->owner($b);

        $this->actingAs($owner)->get('/store')->assertOk()
            ->assertSee('Alpha Hardware')->assertDontSee('Beta Hardware');
        $this->get('/store/'.$a->id)->assertOk()->assertSee('Alpha Hardware')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->get('/store/'.$b->id)->assertNotFound();
        $this->expectException(AuthorizationException::class);
        app(StoreContext::class)->id();
    }

    public function test_suspended_store_and_revoked_membership_are_inaccessible(): void
    {
        $store = Store::factory()->create(['status' => 'suspended']);
        $owner = $this->owner($store);
        $this->actingAs($owner)->get('/store/'.$store->id)->assertNotFound();

        $store->update(['status' => 'active']);
        $owner->memberships()->update(['status' => 'revoked']);
        $this->get('/store/'.$store->id)->assertNotFound();
    }

    public function test_store_queries_fail_without_verified_context(): void
    {
        $this->expectException(AuthorizationException::class);
        StoreModule::query()->count();
    }

    public function test_store_context_scopes_reads_and_prevents_ownership_change(): void
    {
        $a = Store::factory()->create();
        $b = Store::factory()->create();
        $context = app(StoreContext::class);
        $context->set($a);
        $module = StoreModule::create(['module_key' => 'catalog']);
        $context->set($b);
        StoreModule::create(['module_key' => 'sales']);

        $this->assertSame(['sales'], StoreModule::pluck('module_key')->all());
        $module->store_id = $b->id;
        $this->expectException(AuthorizationException::class);
        $module->save();
    }

    public function test_store_model_cannot_be_deleted_from_a_different_context(): void
    {
        $context = app(StoreContext::class);
        $context->set(Store::factory()->create());
        $module = StoreModule::create(['module_key' => 'catalog']);
        $context->set(Store::factory()->create());

        $this->expectException(AuthorizationException::class);
        $module->delete();
    }

    public function test_database_rejects_a_second_active_owner(): void
    {
        $store = Store::factory()->create();
        $this->owner($store);
        $other = User::factory()->create();

        $this->expectException(QueryException::class);
        $other->memberships()->create([
            'store_id' => $store->id, 'role' => 'owner', 'status' => 'active',
        ]);
    }

    public function test_database_retains_revoked_owner_history_and_rejects_invalid_store_reference(): void
    {
        $store = Store::factory()->create();
        $owner = $this->owner($store);
        $owner->memberships()->update(['status' => 'revoked']);
        $this->owner($store);
        $this->assertSame(2, StoreMembership::count());

        $this->expectException(QueryException::class);
        DB::table('store_modules')->insert([
            'id' => (string) Str::ulid(),
            'store_id' => (string) Str::ulid(),
            'module_key' => 'catalog',
        ]);
    }

    public function test_job_context_is_cleared_after_failure_and_between_stores(): void
    {
        $a = Store::factory()->create();
        $b = Store::factory()->create();
        $ownerA = $this->owner($a);
        $ownerB = $this->owner($b);
        $context = app(StoreContext::class);

        try {
            (new UseStoreContext($a->id, $ownerA->id))->handle(new \stdClass, function () use ($context, $a): void {
                $this->assertSame($a->id, $context->id());
                throw new \RuntimeException('Simulated job failure');
            });
        } catch (\RuntimeException) {
            // The next job must establish its own authorized context.
        }

        (new UseStoreContext($b->id, $ownerB->id))->handle(new \stdClass, function () use ($context, $b): void {
            $this->assertSame($b->id, $context->id());
        });

        $this->expectException(AuthorizationException::class);
        $context->id();
    }
}
