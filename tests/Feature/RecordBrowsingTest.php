<?php

namespace Tests\Feature;

use App\Models\BusinessDocument;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Models\StoreModule;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StoreAccess;
use App\Support\Tenancy\StoreContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecordBrowsingTest extends TestCase
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
        $this->actingAs($this->owner);
    }

    public function test_product_pages_keep_filters_and_do_not_include_another_stores_products(): void
    {
        for ($i = 1; $i <= 26; $i++) {
            Product::create(['name' => sprintf('Hammer %02d', $i), 'sku' => 'H-'.$i, 'base_unit' => 'piece', 'price_minor' => 100]);
        }
        app(StoreContext::class)->set(Store::factory()->create());
        Product::create(['name' => 'Hammer from another store', 'sku' => 'OTHER', 'base_unit' => 'piece', 'price_minor' => 100]);
        app(StoreContext::class)->clear();
        $url = route('store.page', [$this->store, 'products']);
        $first = $this->get($url.'?q=Hammer')->assertOk()->assertDontSee('Hammer from another store');
        $this->assertCount(10, $first->viewData('products'));
        $this->assertSame(26, $first->viewData('products')->total());
        $first->assertSee('q=Hammer&amp;page=2#products', false);
        $second = $this->get($url.'?q=Hammer&page=2')->assertOk();
        $this->assertSame('Hammer 11', $second->viewData('products')->first()->name);
        $this->assertCount(25, $this->get($url.'?per_page=25')->assertOk()->viewData('products'));
        $this->getJson($url.'?per_page=100000')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    public function test_product_edit_validation_reopens_the_dialog_and_keeps_the_search(): void
    {
        $product = Product::create(['name' => 'Hammer', 'sku' => 'HAMMER', 'base_unit' => 'piece', 'price_minor' => 100]);
        app(StoreContext::class)->clear();
        $url = route('store.page', [$this->store, 'products']).'?q=Hammer';
        $this->from($url)->post(route('store.products.save', $this->store), [
            '_form' => 'product-'.$product->id, 'product_id' => $product->id,
            'name' => 'Unsaved hammer', 'base_unit' => 'piece', 'price' => '-1',
        ])->assertSessionHasErrors('price')->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('x-init="$el.showModal()"', false)->assertSee('Unsaved hammer');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Hammer', 'price_minor' => 100]);
    }

    public function test_supplier_pagination_does_not_limit_purchase_choices_and_searches_stay_independent(): void
    {
        for ($i = 1; $i <= 26; $i++) {
            $supplier = Supplier::create(['name' => sprintf('Supplier %02d', $i)]);
            PurchaseOrder::create(['supplier_id' => $supplier->id, 'supplier_name' => $supplier->name, 'request_key' => (string) Str::uuid(), 'notes' => 'delivery reference']);
        }
        Supplier::create(['name' => 'Archived supplier', 'active' => false]);
        app(StoreContext::class)->clear();
        $url = route('store.page', [$this->store, 'purchasing']);
        $page = $this->get($url.'?suppliers_page=2&order_q=delivery')->assertOk();
        $this->assertCount(10, $page->viewData('suppliers'));
        $this->assertCount(26, $page->viewData('supplierChoices'));
        $this->assertSame(26, $page->viewData('orders')->total());
        $page->assertSee('Supplier 26');
        $filtered = $this->get($url.'?supplier_q=26&order_q=delivery&orders_page=2')->assertOk();
        $this->assertSame(1, $filtered->viewData('suppliers')->total());
        $this->assertSame(2, $filtered->viewData('orders')->currentPage());
        $this->assertCount(26, $filtered->viewData('supplierChoices'));
    }

    public function test_paged_cash_history_keeps_complete_reconciliation_and_csv(): void
    {
        $session = CashSession::create(['user_id' => $this->owner->id, 'request_key' => (string) Str::uuid(), 'opening_minor' => 50000, 'expected_minor' => 52600, 'opened_at' => now()]);
        for ($i = 1; $i <= 26; $i++) {
            CashMovement::create(['request_key' => (string) Str::uuid(), 'cash_session_id' => $session->id, 'amount_minor' => 100, 'reason' => 'Manual addition '.$i]);
        }
        app(StoreContext::class)->clear();
        $drawer = $this->get(route('store.page', [$this->store, 'cash']))->assertOk();
        $this->assertCount(10, $drawer->viewData('movements'));
        $this->assertSame(52600, $drawer->viewData('session')->expected_minor);
        $url = route('store.cash.show', [$this->store, $session]);
        $report = $this->get($url.'?page=2')->assertOk();
        $this->assertCount(10, $report->viewData('movements'));
        $this->assertSame(2600, $report->viewData('cashIn'));
        $csv = $this->get($url.'?export=1&page=2')->assertOk()->streamedContent();
        $this->assertSame(26, substr_count($csv, 'Manual addition'));
    }

    public function test_store_directory_paging_and_search_do_not_truncate_platform_totals_or_exports(): void
    {
        $admin = User::factory()->withTwoFactor()->create();
        foreach (StoreAccess::PERMISSIONS as $permission) {
            $admin->platformPermissions()->create(['permission' => $permission]);
        }
        for ($i = 1; $i <= 12; $i++) {
            $store = Store::factory()->create(['display_name' => sprintf('Directory shop %02d', $i)]);
            app(StoreContext::class)->set($store);
            BusinessDocument::create(['fingerprint' => hash('sha256', 'synthetic fixture'), 'kind' => 'sale', 'user_id' => $this->owner->id, 'request_key' => (string) Str::uuid(), 'gross_minor' => 100, 'total_minor' => 100, 'metadata' => [], 'posted_at' => now()]);
        }
        app(StoreContext::class)->clear();
        $this->actingAs($admin)->withSession(['platform.mfa' => ['user_id' => $admin->id, 'fingerprint' => hash('sha256', $admin->two_factor_secret)]]);
        $page = $this->get('/admin?page=2')->assertOk();
        $this->assertSame(13, $page->viewData('storeRecords')->total());
        $this->assertSame(1200, array_sum(array_column($page->viewData('metrics'), 'net_sales')));
        $filtered = $this->get('/admin?store_q=Directory%20shop%2012')->assertOk();
        $this->assertSame(1, $filtered->viewData('storeRecords')->total());
        $this->assertSame(1200, array_sum(array_column($filtered->viewData('metrics'), 'net_sales')));
        $csv = $this->get('/admin?export=1&page=2&store_q=Directory%20shop%2012')->assertOk()->streamedContent();
        $this->assertStringContainsString('Directory shop 01', $csv);
        $this->assertStringContainsString('Directory shop 12', $csv);
    }
}
