<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\BusinessDocument;
use App\Models\CashSession;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Store;
use App\Models\StoreModule;
use App\Models\User;
use App\Services\StoreAccess;
use App\Support\Tenancy\StoreContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OnboardingChecklistTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = Store::factory()->create();
        $this->owner = User::factory()->create();
        $this->owner->memberships()->create(['store_id' => $this->store->id, 'role' => 'owner', 'status' => 'active']);
        app(StoreContext::class)->set($this->store);
        foreach (StoreAccess::MODULES as $module) {
            StoreModule::create(['module_key' => $module, 'mode' => 'enabled']);
        }
        app(StoreContext::class)->clear();
        $this->actingAs($this->owner);
    }

    /** @return array<string, mixed> */
    private function progress(): array
    {
        return $this->get(route('store.dashboard', $this->store))->assertOk()->viewData('onboarding');
    }

    public function test_real_workflow_updates_each_step_and_daily_closing_does_not_reset_it(): void
    {
        $initial = $this->progress();
        $this->assertSame(0, $initial['completed']);
        $this->assertSame(6, $initial['total']);
        $this->assertSame('profile', $initial['next']['key']);
        $this->assertTrue($initial['expanded']);

        $this->post(route('store.profile.save', $this->store), ['address_line_1' => '', 'city' => 'La Paz'])->assertSessionHasErrors('address_line_1');
        $this->assertSame(0, $this->progress()['completed']);
        $this->post(route('store.profile.save', $this->store), ['address_line_1' => 'Main Street', 'city' => 'La Paz'])->assertSessionHasNoErrors();
        $this->assertSame('payments', $this->progress()['next']['key']);
        $this->post(route('store.payments.settings', $this->store), ['checkout_methods' => ['cash']])->assertSessionHasNoErrors();
        $this->assertSame(2, $this->progress()['completed']);
        $this->post(route('store.products.save', $this->store), ['name' => 'Claw hammer', 'base_unit' => 'piece', 'price' => '15'])->assertSessionHasNoErrors();
        $this->assertSame('inventory', $this->progress()['next']['key']);
        $product = Product::withoutGlobalScopes()->where('store_id', $this->store->id)->firstOrFail();
        $unit = ProductUnit::withoutGlobalScopes()->where('product_id', $product->id)->firstOrFail();
        $this->post(route('store.post', [$this->store, 'adjustment']), ['request_key' => (string) Str::uuid(), 'product_id' => $product->id, 'stock_version' => 0, 'mode' => 'opening', 'quantity' => '2', 'unit_cost' => '10', 'notes' => 'Counted opening stock'])->assertSessionHasNoErrors();
        $this->assertSame(4, $this->progress()['completed']);
        $this->post(route('store.cash', [$this->store, 'open']), ['request_key' => (string) Str::uuid(), 'amount' => '0'])->assertSessionHasNoErrors();
        $this->assertSame('sale', $this->progress()['next']['key']);
        $session = CashSession::withoutGlobalScopes()->where('store_id', $this->store->id)->firstOrFail();
        $this->post(route('store.post', [$this->store, 'sale']), [
            'request_key' => (string) Str::uuid(), 'cash_session_id' => $session->id,
            'lines' => [['product_id' => $product->id, 'unit_id' => $unit->id, 'quantity' => '2', 'price' => '15', 'discount' => '0', 'discount_type' => 'amount']],
            'payments' => [['method' => 'cash', 'amount' => '30']],
        ])->assertSessionHasNoErrors();
        $this->post(route('store.cash', [$this->store, 'close']), ['request_key' => (string) Str::uuid(), 'session_id' => $session->id, 'amount' => '30'])->assertSessionHasNoErrors();
        $complete = $this->progress();
        $this->assertSame(6, $complete['completed']);
        $this->assertSame(100, $complete['percent']);
        $this->assertTrue($complete['complete']);
        $this->assertFalse($complete['expanded']);
        $this->assertNull($complete['next']);
        $this->assertSame(0, $product->fresh()->quantity_micro);
        $this->get(route('store.page', [$this->store, 'profile']))->assertOk()->assertSee(route('store.dashboard', $this->store).'#setup-checklist', false);
    }

    public function test_store_progress_is_scoped_and_cannot_be_marked_complete_by_query_input(): void
    {
        $other = Store::factory()->create();
        $this->seedMilestones($other);
        $progress = $this->get(route('store.dashboard', [$this->store, 'complete' => 1]))->assertOk()->viewData('onboarding');
        $this->assertSame(0, $progress['completed']);
        $this->assertTrue($progress['expanded']);
        $this->assertSame(0, AuditEvent::where('store_id', $this->store->id)->count());
        $this->get(route('store.dashboard', $other))->assertNotFound();
    }

    public function test_only_enabled_features_are_counted_and_module_changes_are_reflected(): void
    {
        StoreModule::withoutGlobalScopes()->where('store_id', $this->store->id)->update(['mode' => 'disabled']);
        StoreModule::withoutGlobalScopes()->where('store_id', $this->store->id)->where('module_key', 'cash')->update(['mode' => 'restricted']);
        $progress = $this->progress();
        $this->assertSame(['profile'], array_column($progress['steps'], 'key'));
        $this->assertSame(1, $progress['total']);
        StoreModule::withoutGlobalScopes()->where('store_id', $this->store->id)->where('module_key', 'catalog')->update(['mode' => 'enabled']);
        $this->assertSame(['profile', 'products'], array_column($this->progress()['steps'], 'key'));
        StoreModule::withoutGlobalScopes()->where('store_id', $this->store->id)->update(['mode' => 'enabled']);
        $this->assertSame(6, $this->progress()['total']);
    }

    public function test_existing_receiving_and_closed_cash_count_as_completed_milestones(): void
    {
        $this->seedMilestones($this->store);
        $progress = $this->progress();
        $this->assertSame(6, $progress['completed']);
        $this->assertFalse($progress['expanded']);
        // Reviewing contact details is distinct from an administrator provisioning them.
        AuditEvent::where('store_id', $this->store->id)->where('action', 'store.profile_updated')->delete();
        StoreAccess::audit($this->owner, $this->store->id, 'store.updated', $this->store->id);
        $progress = $this->progress();
        $this->assertSame('profile', $progress['next']['key']);
        $this->assertFalse($progress['expanded']);
    }

    public function test_admin_can_view_progress_without_owner_actions_or_an_identity_switch(): void
    {
        $admin = User::factory()->withTwoFactor()->create();
        foreach (StoreAccess::PERMISSIONS as $permission) {
            $admin->platformPermissions()->create(['permission' => $permission]);
        }
        $this->actingAs($admin)->withSession(['platform.mfa' => ['user_id' => $admin->id, 'fingerprint' => hash('sha256', $admin->two_factor_secret)]]);
        $response = $this->get(route('admin.stores.view.dashboard', $this->store))->assertOk()->assertSee('NEXT FOR THE OWNER');
        $this->assertSame(0, $response->viewData('onboarding')['completed']);
        $response->assertSee(route('admin.stores.view.page', [$this->store, 'profile']).'#store-details', false)
            ->assertDontSee(route('store.page', [$this->store, 'profile']), false)->assertDontSee('New sale');
        $this->assertAuthenticatedAs($admin);
        $this->assertFalse(app(StoreContext::class)->isReadOnly());
        $this->assertSame(1, AuditEvent::where('action', 'platform.store_viewed')->where('store_id', $this->store->id)->count());
    }

    private function seedMilestones(Store $store): void
    {
        app(StoreContext::class)->set($store);
        try {
            foreach (['store.profile_updated', 'store.payment_methods_updated'] as $action) {
                StoreAccess::audit($this->owner, $store->id, $action, $store->id);
            }
            $product = Product::create(['name' => 'Existing product', 'sku' => 'EXISTING', 'base_unit' => 'piece', 'price_minor' => 100, 'active' => false]);
            $receipt = BusinessDocument::create(['kind' => 'receipt', 'request_key' => (string) Str::uuid(), 'fingerprint' => hash('sha256', 'fixture'), 'user_id' => $this->owner->id, 'metadata' => [], 'posted_at' => now()]);
            InventoryMovement::create(['product_id' => $product->id, 'business_document_id' => $receipt->id, 'quantity_micro' => 1000000, 'value_micro' => 0, 'stock_version' => 1, 'reason' => 'Received stock']);
            CashSession::create(['user_id' => $this->owner->id, 'request_key' => (string) Str::uuid(), 'opening_minor' => 0, 'expected_minor' => 0, 'opened_at' => now()->subDay(), 'closed_at' => now()]);
            BusinessDocument::create(['kind' => 'sale', 'request_key' => (string) Str::uuid(), 'fingerprint' => hash('sha256', 'fixture'), 'user_id' => $this->owner->id, 'metadata' => [], 'posted_at' => now()]);
        } finally {
            app(StoreContext::class)->clear();
        }
    }
}
