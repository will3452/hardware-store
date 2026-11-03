<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\BusinessDocument;
use App\Models\CashSession;
use App\Models\DocumentAttachment;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Store;
use App\Models\StoreModule;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Operations;
use App\Services\Purchasing;
use App\Services\StoreAccess;
use App\Support\Tenancy\StoreContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdminStoreViewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** @var array<string, mixed> */
    private array $alpha;

    /** @var array<string, mixed> */
    private array $beta;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->alpha = $this->fixture('Alpha');
        $this->beta = $this->fixture('Beta');
        $this->admin = User::factory()->withTwoFactor()->create(['name' => 'Platform Inspector']);
        foreach (StoreAccess::PERMISSIONS as $permission) {
            $this->admin->platformPermissions()->create(['permission' => $permission]);
        }
        $this->signInAdmin();
    }

    /** @return array<string, mixed> */
    private function fixture(string $name): array
    {
        $store = Store::factory()->create(['display_name' => $name.' Hardware', 'status' => 'active']);
        $owner = User::factory()->create();
        $owner->memberships()->create(['store_id' => $store->id, 'role' => 'owner', 'status' => 'active']);
        $context = app(StoreContext::class);
        $context->set($store);
        try {
            foreach (StoreAccess::MODULES as $module) {
                StoreModule::create(['module_key' => $module, 'mode' => 'enabled']);
            }
            $product = Product::create(['sku' => $name.'-HAMMER', 'name' => $name.' claw hammer', 'base_unit' => 'piece', 'price_minor' => 1500]);
            $unit = ProductUnit::create(['product_id' => $product->id, 'label' => 'piece', 'factor_micro' => 1000000]);
            $cash = CashSession::create(['user_id' => $owner->id, 'request_key' => (string) Str::uuid(), 'opening_minor' => 50000, 'expected_minor' => 50000, 'opened_at' => now()]);
            app(Operations::class)->post($owner, 'adjustment', ['request_key' => (string) Str::uuid(), 'product_id' => $product->id, 'mode' => 'opening', 'stock_version' => 0, 'quantity' => '20', 'unit_cost' => '10', 'notes' => $name.' opening stock']);
            $sale = app(Operations::class)->post($owner, 'sale', [
                'request_key' => (string) Str::uuid(), 'cash_session_id' => $cash->id,
                'customer' => $name.' customer', 'lines' => [['product_id' => $product->id, 'unit_id' => $unit->id, 'quantity' => '2', 'price' => '15', 'discount' => '0', 'discount_type' => 'amount']],
                'payments' => [['method' => 'cash', 'amount' => '30']],
            ]);
            $supplier = Supplier::create(['name' => $name.' supplier', 'contact' => $name.' contact']);
            $order = app(Purchasing::class)->draft($owner, ['request_key' => (string) Str::uuid(), 'supplier_id' => $supplier->id,
                'lines' => [['product_id' => $product->id, 'unit_id' => $unit->id, 'quantity' => '10', 'cost' => '10']]]);
            $path = 'attachments/'.$store->id.'/proof.txt';
            Storage::disk('local')->put($path, $name.' private attachment');
            $attachment = DocumentAttachment::create(['business_document_id' => $sale->id, 'user_id' => $owner->id, 'path' => $path, 'name' => 'proof.txt', 'mime' => 'text/plain', 'size' => 24]);

            return compact('store', 'owner', 'product', 'unit', 'sale', 'supplier', 'order', 'cash', 'attachment');
        } finally {
            $context->clear();
        }
    }

    private function signInAdmin(): void
    {
        $this->actingAs($this->admin)->withSession(['platform.mfa' => [
            'user_id' => $this->admin->id, 'fingerprint' => hash('sha256', $this->admin->two_factor_secret),
        ]]);
    }

    public function test_all_workspace_pages_are_read_only_and_access_is_audited_as_the_admin(): void
    {
        $store = $this->alpha['store'];
        $memberships = DB::table('store_memberships')->count();
        foreach (['overview', 'products', 'inventory', 'purchasing', 'sales', 'cash', 'expenses', 'reports', 'audit', 'profile'] as $page) {
            $response = $this->get(route('admin.stores.view.page', [$store, $page]))
                ->assertOk()->assertSee('Viewing as administrator')->assertSee('Platform Inspector')
                ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
            $this->assertDoesNotMatchRegularExpression('~<form[^>]*action="[^\"]*/store/'.$store->id.'/~', $response->getContent());
            $response->assertSee(route('admin.stores.view.page', [$store, 'sales']), false)
                ->assertDontSee(route('store.page', [$store, 'sales']), false);
            $this->assertAuthenticatedAs($this->admin);
            $this->assertFalse(app(StoreContext::class)->isReadOnly());
        }
        $this->assertSame($memberships, DB::table('store_memberships')->count());
        $this->assertSame(10, AuditEvent::where('action', 'platform.store_viewed')->where('store_id', $store->id)->where('user_id', $this->admin->id)->count());
        $this->assertSame($this->alpha['owner']->id, DB::table('store_memberships')->where('store_id', $store->id)->value('user_id'));
    }

    public function test_store_entry_links_and_details_preserve_admin_navigation(): void
    {
        $store = $this->alpha['store'];
        $this->get(route('admin.dashboard'))->assertOk()->assertSee(route('admin.stores.view.dashboard', $store), false);
        $this->get(route('admin.stores.show', $store))->assertOk()->assertSee('View store');
        $this->get(route('admin.stores.view.document', [$store, $this->alpha['sale']]))->assertOk()->assertSee('Alpha claw hammer')
            ->assertDontSee('Return items &amp; record refund', false)->assertDontSee('Upload private attachment')
            ->assertSee(route('admin.stores.view.attachments.download', [$store, $this->alpha['attachment']]), false);
        $this->get(route('admin.stores.view.order', [$store, $this->alpha['order']]))->assertOk()->assertDontSee('Edit draft order')->assertDontSee('Update order');
        DB::table('purchase_orders')->where('id', $this->alpha['order']->id)->update(['status' => 'placed']);
        $this->get(route('admin.stores.view.order', [$store, $this->alpha['order']]))->assertOk()->assertDontSee('Receive now')->assertDontSee('Post received quantities');
        $this->get(route('admin.stores.view.cash.show', [$store, $this->alpha['cash']]))->assertOk()->assertSee('Expected cash');
        $this->get(route('admin.stores.view.attachments.download', [$store, $this->alpha['attachment']]))->assertOk()->assertStreamedContent('Alpha private attachment');
    }

    public function test_filters_and_records_stay_scoped_to_the_selected_store(): void
    {
        $store = $this->alpha['store'];
        $this->get(route('admin.stores.view.page', [$store, 'products', 'q' => 'claw']))->assertOk()->assertSee('Alpha claw hammer')->assertDontSee('Beta claw hammer');
        $this->get(route('admin.stores.view.page', [$store, 'sales', 'history_q' => 'Alpha customer']))->assertOk()->assertSee('Alpha customer')->assertDontSee('Beta customer');
        foreach (['document' => 'sale', 'order' => 'order', 'cash.show' => 'cash', 'attachments.download' => 'attachment'] as $route => $key) {
            $this->get(route('admin.stores.view.'.$route, [$store, $this->beta[$key]]))->assertNotFound();
        }
        $this->get(route('admin.stores.view.page', [$this->beta['store'], 'products']))->assertOk()->assertSee('Beta claw hammer')->assertDontSee('Alpha claw hammer');
        $this->assertSame(3, AuditEvent::where('action', 'platform.store_viewed')->count());
    }

    public function test_exports_require_separate_permission_and_stream_only_the_selected_store(): void
    {
        $store = $this->alpha['store'];
        foreach (['1', 'products', 'inventory'] as $export) {
            $response = $this->get(route('admin.stores.view.page', [$store, 'reports', 'export' => $export]))->assertOk();
            $csv = $response->streamedContent();
            $this->assertStringContainsString('Alpha', $csv);
            $this->assertStringNotContainsString('Beta', $csv);
        }
        $csv = $this->get(route('admin.stores.view.cash.show', [$store, $this->alpha['cash'], 'export' => 1]))->assertOk()->streamedContent();
        $this->assertStringContainsString('Alpha Hardware', $csv);
        $this->assertStringNotContainsString('Beta', $csv);
        $this->admin->platformPermissions()->where('permission', 'platform.reports.export')->delete();
        $this->get(route('admin.stores.view.page', [$store, 'reports']))->assertOk()->assertDontSee('Export CSV')->assertDontSee('Export product CSV');
        $this->get(route('admin.stores.view.page', [$store, 'reports', 'export' => 'products']))->assertForbidden();
        $this->get(route('admin.stores.view.cash.show', [$store, $this->alpha['cash'], 'export' => 1]))->assertForbidden();
    }

    public function test_view_permission_and_current_mfa_proof_are_required_on_every_request(): void
    {
        $url = route('admin.stores.view.dashboard', $this->alpha['store']);
        $this->get($url)->assertOk();
        $this->admin->platformPermissions()->where('permission', 'platform.stores.view')->delete();
        $this->get($url)->assertForbidden();
        $this->get(route('admin.dashboard'))->assertDontSee('View store');
        $this->admin->platformPermissions()->create(['permission' => 'platform.stores.view']);
        $this->withSession(['platform.mfa' => []])->get($url)->assertRedirect(route('login'));
        $this->signInAdmin();
        $this->admin->forceFill(['status' => 'disabled'])->save();
        $this->get($url)->assertForbidden();
    }

    public function test_owner_cannot_enter_admin_view_and_admin_does_not_acquire_owner_access(): void
    {
        $this->get(route('store.page', [$this->alpha['store'], 'products']))->assertNotFound();
        $this->actingAs($this->alpha['owner'])->get(route('admin.stores.view.dashboard', $this->alpha['store']))->assertForbidden();
        $this->get(route('store.page', [$this->alpha['store'], 'products']))->assertOk()->assertSee('Add a product')->assertDontSee('Viewing as administrator');
        $this->get(route('store.page', [$this->beta['store'], 'products']))->assertNotFound();
    }

    public function test_suspended_archived_pending_and_disabled_modules_can_be_inspected(): void
    {
        DB::table('store_modules')->where('store_id', $this->alpha['store']->id)->update(['mode' => 'disabled']);
        foreach (['suspended', 'archived', 'pending_activation'] as $status) {
            $this->alpha['store']->update(['status' => $status]);
            $this->get(route('admin.stores.view.dashboard', $this->alpha['store']))->assertOk()->assertSee('Read-only');
            $this->get(route('admin.stores.view.page', [$this->alpha['store'], 'reports']))->assertOk();
        }
        $this->actingAs($this->alpha['owner'])->get(route('store.dashboard', $this->alpha['store']))->assertNotFound();
    }

    public function test_forged_write_requests_cannot_change_store_records(): void
    {
        $store = $this->alpha['store'];
        $before = DB::table('products')->where('id', $this->alpha['product']->id)->first();
        $documents = BusinessDocument::withoutGlobalScopes()->count();
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->call($method, route('admin.stores.view.dashboard', $store))->assertStatus(405);
        }
        $writes = [
            ['store.products.save', [$store]], ['store.units.save', [$store]], ['store.post', [$store, 'sale']],
            ['store.cash', [$store, 'close']], ['store.suppliers.save', [$store]], ['store.purchases.save', [$store]],
            ['store.purchases.update', [$store, $this->alpha['order']]], ['store.order.status', [$store, $this->alpha['order']]],
            ['store.profile.save', [$store]], ['store.payments.settings', [$store]], ['store.attachments.upload', [$store, $this->alpha['sale']]],
        ];
        foreach ($writes as [$name, $parameters]) {
            $this->post(route($name, $parameters), ['name' => 'Tampered'])->assertNotFound();
        }
        $this->assertEquals($before, DB::table('products')->where('id', $before->id)->first());
        $this->assertSame($documents, BusinessDocument::withoutGlobalScopes()->count());
    }

    public function test_read_only_context_blocks_services_models_and_future_write_routes_even_for_an_owner(): void
    {
        $context = app(StoreContext::class);
        $context->set($this->alpha['store'], readOnly: true);
        try {
            app(StoreAccess::class)->lock($this->alpha['owner']);
            $this->fail('Write service accepted an inspection context.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        foreach (['save', 'delete'] as $method) {
            try {
                $this->alpha['product']->{$method}();
                $this->fail('Model write accepted an inspection context.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
        $context->clear();
        Route::post('/admin-view-test/{store}', fn () => response('Should never run'))->middleware(['web', 'auth', 'verified', 'platform.admin', 'store.admin-view']);
        $this->post('/admin-view-test/'.$this->alpha['store']->id)->assertForbidden();
        $this->assertFalse($context->isReadOnly());
        $this->expectException(AuthorizationException::class);
        $context->id();
    }

    public function test_context_does_not_leak_between_admin_view_and_legitimate_owner_tabs(): void
    {
        $this->alpha['owner']->platformPermissions()->create(['permission' => 'platform.portal.access']);
        $this->alpha['owner']->platformPermissions()->create(['permission' => 'platform.stores.view']);
        $this->alpha['owner']->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();
        $this->admin = $this->alpha['owner'];
        $this->signInAdmin();
        $this->get(route('admin.stores.view.page', [$this->alpha['store'], 'products']))->assertOk()->assertDontSee('Add a product');
        $this->get(route('store.page', [$this->alpha['store'], 'products']))->assertOk()->assertSee('Add a product');
        $this->get(route('admin.stores.view.document', [$this->alpha['store'], $this->beta['sale']]))->assertNotFound();
        $this->get(route('store.page', [$this->alpha['store'], 'products']))->assertOk()->assertSee('Add a product');
    }

    public function test_permission_migration_grants_existing_full_admins_only(): void
    {
        $this->admin->platformPermissions()->where('permission', 'platform.stores.view')->delete();
        $limited = User::factory()->create();
        foreach (['platform.portal.access', 'platform.stores.manage', 'platform.reports.read'] as $permission) {
            $limited->platformPermissions()->create(['permission' => $permission]);
        }
        $migration = require database_path('migrations/2026_11_03_140000_add_admin_store_view_permission.php');
        $migration->up();
        $migration->up();
        $this->assertTrue($this->admin->hasPlatformPermission('platform.stores.view'));
        $this->assertFalse($limited->hasPlatformPermission('platform.stores.view'));
        $this->assertFalse($this->alpha['owner']->hasPlatformPermission('platform.stores.view'));
        $this->assertSame(1, $this->admin->platformPermissions()->where('permission', 'platform.stores.view')->count());
    }
}
