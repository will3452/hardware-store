<?php

namespace Tests\Feature;

use App\Models\BusinessDocument;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\SalesOrder;
use App\Models\Store;
use App\Models\StoreModule;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing;
use App\Services\Reports;
use App\Services\StoreAccess;
use App\Support\Tenancy\StoreContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class HardwareGrowthTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private User $owner;

    private Product $product;

    private ProductUnit $unit;

    private Customer $customer;

    private string $sessionId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = Store::factory()->create(['timezone' => 'Asia/Manila']);
        $this->owner = User::factory()->create();
        $this->owner->memberships()->create(['store_id' => $this->store->id, 'role' => 'owner', 'status' => 'active']);
        app(StoreContext::class)->set($this->store);
        foreach (StoreAccess::MODULES as $module) {
            StoreModule::create(['module_key' => $module, 'mode' => 'enabled']);
        }
        $this->product = Product::create(['sku' => 'CEMENT', 'name' => 'Cement bag', 'base_unit' => 'bag', 'price_minor' => 25000]);
        $this->unit = ProductUnit::create(['product_id' => $this->product->id, 'label' => 'bag', 'factor_micro' => 1000000]);
        $this->customer = Customer::create(['name' => 'Pilot contractor', 'active' => true, 'credit_enabled' => true, 'credit_limit_minor' => 10000000, 'term_days' => 30]);
        app(StoreContext::class)->clear();
        $this->actingAs($this->owner);
        $this->post(route('store.cash', [$this->store, 'open']), ['request_key' => (string) Str::uuid(), 'amount' => '500'])->assertSessionHasNoErrors();
        $this->sessionId = DB::table('cash_sessions')->value('id');
        $this->postDoc('adjustment', ['product_id' => $this->product->id, 'mode' => 'opening', 'stock_version' => 0, 'quantity' => '200', 'unit_cost' => '100', 'notes' => 'Reviewed fixture']);
    }

    /** @param array<string, mixed> $input */
    private function postDoc(string $kind, array $input): BusinessDocument
    {
        $key = $input['request_key'] ?? (string) Str::uuid();
        $this->post(route('store.post', [$this->store, $kind]), $input + ['request_key' => $key, 'cash_session_id' => $this->sessionId])->assertSessionHasNoErrors()->assertRedirect();

        return BusinessDocument::withoutGlobalScopes()->where('request_key', $key)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function sale(string $quantity = '20', string $price = '250', string $paid = '2000'): array
    {
        return ['request_key' => (string) Str::uuid(), 'cash_session_id' => $this->sessionId, 'customer_id' => $this->customer->id, 'settlement' => 'credit', 'due_date' => now()->addDays(30)->toDateString(), 'lines' => [['product_id' => $this->product->id, 'unit_id' => $this->unit->id, 'quantity' => $quantity, 'price' => $price, 'discount' => '0', 'discount_type' => 'amount']], 'payments' => [['method' => 'cash', 'amount' => $paid]]];
    }

    private function balance(?string $obligation = null): int
    {
        return (int) DB::table('receivable_entries')->where('customer_id', $this->customer->id)->when($obligation, fn ($q) => $q->where('obligation_id', $obligation))->sum('amount_minor');
    }

    public function test_credit_collections_reports_statements_and_retry_reconcile(): void
    {
        $sale = $this->postDoc('sale', $this->sale());
        $this->assertSame(300000, $this->balance());
        $input = ['request_key' => (string) Str::uuid(), 'customer_id' => $this->customer->id, 'amount' => '1000', 'allocations' => [['obligation_id' => $sale->id, 'amount' => '1000']], 'method' => 'cash'];
        $collection = $this->postDoc('collection', $input);
        $this->postDoc('collection', $input);
        $this->assertSame(200000, $this->balance());
        $this->assertDatabaseHas('cash_sessions', ['id' => $this->sessionId, 'expected_minor' => 350000]);
        $metrics = app(Reports::class)->summarize([$this->store->id], now()->subDay()->toDateString(), now()->addDay()->toDateString());
        $this->assertSame(500000, $metrics['net_sales']);
        $this->assertSame(300000, $metrics['collections']);
        $this->assertSame(200000, $metrics['receivables']);
        $this->get(route('store.customer', [$this->store, $this->customer]))->assertOk()->assertSee('Pilot contractor')->assertSee('2,000.00');
        $export = $this->get(route('store.customer', [$this->store, $this->customer]).'?export=1')->assertOk();
        $this->assertStringContainsString('collection', $export->streamedContent());
        $this->get(route('store.document', [$this->store, $collection]))->assertOk();
        $this->postDoc('collection_reversal', ['original_id' => $collection->id, 'notes' => 'Payment returned']);
        $this->assertSame(300000, $this->balance());
        $this->assertDatabaseHas('cash_sessions', ['id' => $this->sessionId, 'expected_minor' => 250000]);
    }

    public function test_credit_return_reduces_debt_before_refunding_and_keeps_cost(): void
    {
        $sale = $this->postDoc('sale', $this->sale('10', '100', '300'));
        $line = DB::table('document_lines')->where('business_document_id', $sale->id)->first();
        $return = $this->postDoc('return', ['original_id' => $sale->id, 'lines' => [['source_line_id' => $line->id, 'quantity' => '9', 'disposition' => 'sellable']], 'method' => 'cash', 'notes' => 'Returned goods']);
        $this->assertSame(0, $this->balance());
        $this->assertSame(70000, $return->metadata['debt_reduction_minor']);
        $this->assertSame(20000, $return->metadata['refund_minor']);
        $this->assertDatabaseHas('payments', ['business_document_id' => $return->id, 'amount_minor' => 20000]);
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'quantity_micro' => 199000000]);
        $metrics = app(Reports::class)->summarize([$this->store->id], now()->subDay()->toDateString(), now()->addDay()->toDateString());
        $this->assertSame(10000, $metrics['net_sales']);
        $this->assertSame(30000, $metrics['collections']);
        $this->assertSame(20000, $metrics['refunds']);
        $this->get(route('store.document', [$this->store, $return]))->assertOk()->assertSee('Debt reduced:');
    }

    public function test_legacy_paid_sales_need_no_customer_and_restricted_credit_does_not_break_checkout(): void
    {
        DB::table('store_modules')->where('store_id', $this->store->id)->whereIn('module_key', ['customers', 'credit', 'orders'])->update(['mode' => 'disabled']);
        $data = $this->sale('2', '250', '500');
        unset($data['customer_id'], $data['settlement'], $data['due_date']);
        $sale = $this->postDoc('sale', $data);
        $this->assertNull($sale->customer_id);
        $this->assertDatabaseCount('receivable_entries', 0);
        $this->get(route('store.page', [$this->store, 'sales']))->assertOk()->assertDontSee('Charge balance to customer');
        $this->get(route('store.document', [$this->store, $sale]))->assertOk();
    }

    public function test_credit_limit_and_overcollection_fail_atomically(): void
    {
        DB::table('customers')->where('id', $this->customer->id)->update(['credit_limit_minor' => 10000]);
        $this->post(route('store.post', [$this->store, 'sale']), $this->sale())->assertSessionHasErrors();
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'quantity_micro' => 200000000]);
        $sale = $this->postDoc('sale', $this->sale('1', '100', '0'));
        $this->post(route('store.post', [$this->store, 'collection']), ['request_key' => (string) Str::uuid(), 'cash_session_id' => $this->sessionId, 'customer_id' => $this->customer->id, 'amount' => '101', 'method' => 'cash', 'allocations' => [['obligation_id' => $sale->id, 'amount' => '101']]])->assertSessionHasErrors();
        $this->assertSame(10000, $this->balance());
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_opening_debt_changes_neither_sales_nor_cash_and_can_be_reversed(): void
    {
        $opening = $this->postDoc('opening_receivable', ['customer_id' => $this->customer->id, 'amount' => '2500', 'due_date' => now()->subDays(2)->toDateString(), 'original_date' => now()->subMonth()->toDateString(), 'reference' => 'Reviewed old book', 'notes' => 'Opening balance']);
        $metrics = app(Reports::class)->summarize([$this->store->id], now()->subDay()->toDateString(), now()->timezone($this->store->timezone)->toDateString());
        $this->assertSame(0, $metrics['net_sales']);
        $this->assertSame(0, $metrics['collections']);
        $this->assertSame(250000, $metrics['overdue_receivables']);
        $this->assertDatabaseHas('cash_sessions', ['id' => $this->sessionId, 'expected_minor' => 50000]);
        $this->postDoc('opening_receivable_reversal', ['original_id' => $opening->id, 'notes' => 'Wrong opening reference']);
        $this->assertSame(0, $this->balance());
    }

    /** @param array<string, mixed> $input */
    private function orderAction(string $action, ?SalesOrder $order, array $input = []): SalesOrder
    {
        $key = $input['request_key'] ?? (string) Str::uuid();
        $response = $this->post(route('store.order.action', ['store' => $this->store->id, 'action' => $action, 'salesOrder' => $order?->id]), $input + ['request_key' => $key, 'revision' => $order?->revision]);
        $response->assertSessionHasNoErrors()->assertRedirect();
        $id = DB::table('sales_order_actions')->where('request_key', $key)->value('sales_order_id');

        return SalesOrder::withoutGlobalScopes()->findOrFail($id);
    }

    private function quotation(): SalesOrder
    {
        $input = $this->sale('100', '250', '0');

        return $this->orderAction('save', null, ['customer_id' => $this->customer->id, 'valid_until' => now()->addDays(7)->toDateString(), 'lines' => $input['lines']]);
    }

    /** @return array<string, mixed> */
    private function releaseInput(SalesOrder $order, string $quantity, string $cash): array
    {
        $line = DB::table('sales_order_lines')->where('sales_order_id', $order->id)->first();

        return ['request_key' => (string) Str::uuid(), 'revision' => $order->revision, 'cash_session_id' => $this->sessionId, 'lines' => [['order_line_id' => $line->id, 'quantity' => $quantity]], 'payments' => [['method' => 'cash', 'amount' => $cash]], 'settlement' => 'paid', 'method' => 'delivery', 'recipient' => 'Site foreman', 'address' => 'Pilot site'];
    }

    public function test_quote_partial_release_payment_and_delivery_confirmation_are_independent(): void
    {
        $draft = $this->quotation();
        $this->assertDatabaseCount('business_documents', 1);
        $this->get(route('store.sales-order', [$this->store, $draft]))->assertOk();
        $issued = $this->orderAction('issue', $draft);
        $accepted = $this->orderAction('accept', $issued, ['notes' => 'Accepted by customer']);
        $input = $this->releaseInput($accepted, '40', '10000');
        $partial = $this->orderAction('release', $accepted, $input);
        $this->assertSame('partial', $partial->status);
        $this->orderAction('release', $accepted, $input);
        $this->assertDatabaseCount('order_releases', 1);
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'quantity_micro' => 160000000]);
        $this->get(route('store.sales-order', [$this->store, $partial]))->assertOk()->assertSee('Dispatched');
        $complete = $this->orderAction('release', $partial, $this->releaseInput($partial, '60', '15000'));
        $this->assertSame('completed', $complete->status);
        $release = DB::table('order_releases')->first();
        $this->orderAction('receive', $complete, ['release_id' => $release->id, 'recipient' => 'Actual recipient']);
        $this->assertSame(2, DB::table('business_documents')->where('kind', 'sale')->count());
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'quantity_micro' => 100000000]);
        $metrics = app(Reports::class)->summarize([$this->store->id], now()->subDay()->toDateString(), now()->addDay()->toDateString());
        $this->assertSame(2500000, $metrics['net_sales']);
        $this->assertSame(2500000, $metrics['collections']);
    }

    public function test_completed_orders_with_pending_delivery_keep_resolution_access(): void
    {
        $order = $this->orderAction('accept', $this->orderAction('issue', $this->quotation()), ['notes' => 'Accepted']);
        $order = $this->orderAction('release', $order, $this->releaseInput($order, '100', '25000'));
        $this->assertSame('completed', $order->status);
        $release = DB::table('order_releases')->first();
        $admin = User::factory()->withTwoFactor()->create();
        foreach (StoreAccess::PERMISSIONS as $permission) {
            $admin->platformPermissions()->create(['permission' => $permission]);
        }
        $this->actingAs($admin)->withSession(['platform.mfa' => ['user_id' => $admin->id, 'fingerprint' => hash('sha256', $admin->two_factor_secret)]]);
        $modes = array_fill_keys(StoreAccess::MODULES, 'enabled');
        $modes['orders'] = 'disabled';
        $this->post(route('admin.stores.modules', $this->store), ['modules' => $modes])->assertSessionHasErrors();
        $modes['orders'] = 'restricted';
        $this->post(route('admin.stores.modules', $this->store), ['modules' => $modes])->assertSessionHasNoErrors();
        $this->actingAs($this->owner);
        $this->orderAction('receive', $order, ['release_id' => $release->id, 'recipient' => 'Actual recipient']);
        $this->assertDatabaseHas('order_releases', ['id' => $release->id, 'received_by' => 'Actual recipient']);
        $this->actingAs($admin);
        $modes['orders'] = 'disabled';
        $this->post(route('admin.stores.modules', $this->store), ['modules' => $modes])->assertSessionHasNoErrors();
    }

    public function test_order_failures_roll_back_released_quantities_and_revisions_preserve_history(): void
    {
        $order = $this->orderAction('accept', $this->orderAction('issue', $this->quotation()), ['notes' => 'Accepted']);
        $input = $this->releaseInput($order, '40', '1');
        $this->post(route('store.order.action', [$this->store, 'release', $order]), $input)->assertSessionHasErrors();
        $this->assertDatabaseHas('sales_order_lines', ['sales_order_id' => $order->id, 'released_micro' => 0]);
        $partial = $this->orderAction('release', $order, $this->releaseInput($order, '40', '10000'));
        $replacement = $this->orderAction('revise', $partial, ['notes' => 'Change remaining request']);
        $this->assertSame($partial->id, $replacement->replaces_id);
        $this->assertSame('draft', $replacement->status);
        $this->assertDatabaseHas('sales_order_lines', ['sales_order_id' => $partial->id, 'released_micro' => 40000000, 'cancelled_micro' => 60000000]);
        $this->assertDatabaseHas('sales_order_lines', ['sales_order_id' => $replacement->id, 'quantity_micro' => 60000000, 'total_minor' => 1500000]);
        $this->assertDatabaseCount('order_releases', 1);
    }

    public function test_later_collection_uses_its_own_reporting_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-04 10:00', 'Asia/Manila')->utc());
        $sale = $this->postDoc('sale', $this->sale());
        $this->travelTo(CarbonImmutable::parse('2026-11-05 10:00', 'Asia/Manila')->utc());
        $this->postDoc('collection', ['customer_id' => $this->customer->id, 'amount' => '1000', 'method' => 'cash', 'allocations' => [['obligation_id' => $sale->id, 'amount' => '1000']]]);
        $dayOne = app(Reports::class)->summarize([$this->store->id], '2026-11-04', '2026-11-04');
        $dayTwo = app(Reports::class)->summarize([$this->store->id], '2026-11-05', '2026-11-05');
        $this->assertSame(500000, $dayOne['net_sales']);
        $this->assertSame(200000, $dayOne['collections']);
        $this->assertSame(300000, $dayOne['receivables']);
        $this->assertSame(0, $dayTwo['net_sales']);
        $this->assertSame(100000, $dayTwo['collections']);
        $this->assertSame(200000, $dayTwo['receivables']);
        $this->travelBack();
    }

    public function test_partial_release_rounding_and_names_keep_accepted_snapshots(): void
    {
        $input = $this->sale('3', '1', '0');
        $input['lines'][0]['discount'] = '0.01';
        $order = $this->orderAction('save', null, ['customer_id' => $this->customer->id, 'valid_until' => now()->addWeek()->toDateString(), 'lines' => $input['lines']]);
        $order = $this->orderAction('accept', $this->orderAction('issue', $order), ['notes' => 'Accepted price']);
        DB::table('products')->where('id', $this->product->id)->update(['name' => 'New catalog name', 'price_minor' => 99900]);
        for ($i = 0; $i < 3; $i++) {
            $order = $this->orderAction('release', $order, $this->releaseInput($order, '1', '1'));
        }
        $this->assertSame('completed', $order->status);
        $this->assertEquals(299, DB::table('business_documents')->where('kind', 'sale')->sum('total_minor'));
        $this->assertEquals(1, DB::table('business_documents')->where('kind', 'sale')->sum('discount_minor'));
        $this->assertDatabaseHas('document_lines', ['name' => 'Cement bag', 'price_minor' => 100]);
    }

    public function test_guided_wire_receiving_and_fractional_sale_share_the_same_stock_record(): void
    {
        $this->post(route('store.products.save', $this->store), ['template_key' => 'wire', 'specifications' => ['type' => 'THHN', 'size' => '2', 'size_unit' => 'mm²', 'color' => 'Red'], 'base_unit' => 'meter', 'price' => '25', 'pack_label' => 'roll of 100 m', 'pack_factor' => '100'])->assertSessionHasNoErrors();
        app(StoreContext::class)->set($this->store);
        $wire = Product::where('template_key', 'wire')->firstOrFail();
        $roll = ProductUnit::where('product_id', $wire->id)->where('label', 'roll of 100 m')->firstOrFail();
        $meter = ProductUnit::where('product_id', $wire->id)->where('label', 'meter')->firstOrFail();
        $supplier = Supplier::create(['name' => 'Wire supplier']);
        $po = app(Purchasing::class)->draft($this->owner, ['request_key' => (string) Str::uuid(), 'supplier_id' => $supplier->id, 'lines' => [['product_id' => $wire->id, 'unit_id' => $roll->id, 'quantity' => '2', 'cost' => '1500']]]);
        app(StoreContext::class)->clear();
        $this->post(route('store.order.status', [$this->store, $po]), ['status' => 'placed', 'notes' => 'Place order'])->assertSessionHasNoErrors();
        $poLine = DB::table('purchase_order_lines')->where('purchase_order_id', $po->id)->first();
        $this->postDoc('receipt', ['order_id' => $po->id, 'lines' => [['order_line_id' => $poLine->id, 'quantity' => '2']]]);
        $this->postDoc('sale', ['lines' => [['product_id' => $wire->id, 'unit_id' => $meter->id, 'quantity' => '3.5', 'price' => '25', 'discount' => '0', 'discount_type' => 'amount']], 'payments' => [['method' => 'cash', 'amount' => '100']]]);
        $this->assertDatabaseHas('products', ['id' => $wire->id, 'quantity_micro' => 196500000, 'value_micro' => 2947500000]);
        $this->get(route('store.page', [$this->store, 'reports']))->assertOk();
    }

    public function test_cross_store_customers_and_orders_are_inaccessible_and_admin_is_read_only(): void
    {
        $order = $this->quotation();
        $other = Store::factory()->create();
        $this->get(route('store.customer', [$other, $this->customer]))->assertNotFound();
        app(StoreContext::class)->set($other);
        $outsider = Customer::create(['name' => 'Private other customer']);
        app(StoreContext::class)->clear();
        $this->get(route('store.customer', [$this->store, $outsider]))->assertNotFound();
        $data = $this->sale();
        $data['customer_id'] = $outsider->id;
        $this->post(route('store.post', [$this->store, 'sale']), $data)->assertNotFound();
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'quantity_micro' => 200000000]);
        $admin = User::factory()->withTwoFactor()->create();
        foreach (StoreAccess::PERMISSIONS as $permission) {
            $admin->platformPermissions()->create(['permission' => $permission]);
        }
        $this->actingAs($admin)->withSession(['platform.mfa' => ['user_id' => $admin->id, 'fingerprint' => hash('sha256', $admin->two_factor_secret)]]);
        foreach (['customers', 'orders', 'products', 'reports'] as $page) {
            $this->get(route('admin.stores.view.page', [$this->store, $page]))->assertOk();
        }
        $this->get(route('admin.stores.view.customer', [$this->store, $this->customer]))->assertOk()->assertDontSee('Record reviewed opening debt');
        $this->get(route('admin.stores.view.sales-order', [$this->store, $order]))->assertOk()->assertDontSee('Issue quotation &amp; lock this version', false);
    }
}
