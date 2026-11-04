<?php

namespace Tests\Feature;

use App\Models\BusinessDocument;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\DocumentLine;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Store;
use App\Models\StoreModule;
use App\Models\User;
use App\Services\Operations;
use App\Services\StoreAccess;
use App\Support\Tenancy\StoreContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScannerReceiptTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private User $owner;

    private Product $product;

    private ProductUnit $unit;

    private CashSession $session;

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
        $this->product = Product::create(['sku' => 'WIRE-1', 'barcode' => '00123', 'name' => 'Zinc coated wire with a long specification name', 'base_unit' => 'meter', 'fractional' => true, 'price_minor' => 1250]);
        $this->unit = ProductUnit::create(['product_id' => $this->product->id, 'label' => 'meter', 'factor_micro' => 1000000]);
        $this->session = CashSession::create(['user_id' => $this->owner->id, 'request_key' => (string) Str::uuid(), 'opening_minor' => 10000, 'expected_minor' => 10000, 'opened_at' => now()]);
        app(Operations::class)->post($this->owner, 'adjustment', ['request_key' => (string) Str::uuid(), 'product_id' => $this->product->id, 'mode' => 'opening', 'stock_version' => 0, 'quantity' => '200', 'unit_cost' => '8', 'notes' => 'Synthetic test stock']);
        $this->actingAs($this->owner);
        app(StoreContext::class)->clear();
    }

    private function sale(array $overrides = []): BusinessDocument
    {
        $data = array_replace(['request_key' => (string) Str::uuid(), 'cash_session_id' => $this->session->id, 'lines' => [['product_id' => $this->product->id, 'unit_id' => $this->unit->id, 'quantity' => '2.5', 'price' => '12.50', 'discount' => '0', 'discount_type' => 'amount']], 'payments' => [['method' => 'cash', 'amount' => '50']]], $overrides);
        $this->post(route('store.post', [$this->store, 'sale']), $data)->assertSessionHasNoErrors()->assertRedirect();

        return BusinessDocument::withoutGlobalScopes()->where('request_key', $data['request_key'])->firstOrFail();
    }

    public function test_exact_lookup_preserves_zeroes_bypasses_fuzzy_limit_and_excludes_archived_products(): void
    {
        app(StoreContext::class)->set($this->store);
        for ($i = 0; $i < 35; $i++) {
            Product::create(['sku' => 'OTHER-'.$i, 'name' => 'A wire 00123 '.$i, 'base_unit' => 'piece', 'price_minor' => 100]);
        }
        app(StoreContext::class)->clear();
        $url = route('store.catalog', $this->store);
        $this->getJson($url.'?q=00123&exact=1')->assertJsonCount(1)->assertJsonPath('0.id', $this->product->id)->assertJsonPath('0.barcode', '00123');
        $this->getJson($url.'?q=wire-1&exact=1')->assertJsonCount(1);
        $this->getJson($url.'?q=WIRE&exact=1')->assertExactJson([]);
        $this->getJson($url.'?q=Zinc')->assertJsonCount(1);
        DB::table('products')->where('id', $this->product->id)->update(['active' => false]);
        $this->getJson($url.'?q=00123&exact=1&include_archived=1')->assertExactJson([]);
    }

    public function test_conflicting_barcode_and_sku_are_returned_for_ambiguity_handling(): void
    {
        app(StoreContext::class)->set($this->store);
        Product::create(['sku' => '00123', 'name' => 'Another exact match', 'base_unit' => 'piece', 'price_minor' => 100]);
        app(StoreContext::class)->clear();
        $this->getJson(route('store.catalog', [$this->store, 'q' => '00123', 'exact' => 1]))->assertJsonCount(2);
    }

    public function test_receipt_previews_copies_and_test_print_preserve_paid_sale_snapshots(): void
    {
        $sale = $this->sale();
        $before = DB::table('business_documents')->count();
        DB::table('products')->where('id', $this->product->id)->update(['price_minor' => 9999, 'name' => 'Renamed current product']);
        foreach (['document', '58', '80'] as $paper) {
            $this->get(route('store.receipt', [$this->store, $sale->id, 'paper' => $paper, 'copy' => 1]))->assertOk()->assertSee('COPY · REPRINT')->assertSee('paper-'.$paper)->assertSee('2.5 meter × 12.50')->assertSee('31.25')->assertSee('18.75')->assertSee('Zinc coated wire')->assertDontSee('Renamed current product')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
            $this->get(route('store.print-test', [$this->store, 'paper' => $paper]))->assertOk()->assertSee('TEST PRINT · NOT A SALE');
        }
        $this->get(route('store.receipt', [$this->store, $sale->id, 'paper' => 'script']))->assertSessionHasErrors();
        $this->assertSame($before, DB::table('business_documents')->count());
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'quantity_micro' => 197500000]);
        $this->assertDatabaseHas('cash_sessions', ['id' => $this->session->id, 'expected_minor' => 13125]);
        $this->get(route('store.page', [$this->store, 'sales']))->assertOk()->assertSee('Ready to scan / type')->assertSee('Printer setup & test print', false)->assertDontSee('Offline pilot');
        $this->get(route('store.document', [$this->store, $sale->id]))->assertOk()->assertSee('Print record')->assertSee('58 mm receipt')->assertSee('Reprint a COPY');
    }

    public function test_credit_receipt_includes_agreed_due_date_and_current_balance(): void
    {
        app(StoreContext::class)->set($this->store);
        $customer = Customer::create(['name' => 'Synthetic credit customer', 'credit_enabled' => true, 'credit_limit_minor' => 100000]);
        app(StoreContext::class)->clear();
        $due = now()->addDays(30)->toDateString();
        $sale = $this->sale(['customer_id' => $customer->id, 'settlement' => 'credit', 'due_date' => $due, 'payments' => [['method' => 'cash', 'amount' => '0']]]);
        $this->get(route('store.receipt', [$this->store, $sale->id, 'paper' => 58]))->assertOk()->assertSee($due)->assertSee('Outstanding now: 31.25');
    }

    public function test_return_and_pack_receipts_show_original_units_and_refund(): void
    {
        app(StoreContext::class)->set($this->store);
        $pack = ProductUnit::create(['product_id' => $this->product->id, 'label' => 'roll', 'factor_micro' => 100000000]);
        app(StoreContext::class)->clear();
        $sale = $this->sale(['lines' => [['product_id' => $this->product->id, 'unit_id' => $pack->id, 'quantity' => '1', 'price' => '1250', 'discount' => '50', 'discount_type' => 'amount']], 'payments' => [['method' => 'cash', 'amount' => '1200']]]);
        $this->get(route('store.receipt', [$this->store, $sale->id, 'paper' => 80]))->assertOk()->assertSee('1 roll × 1,250.00')->assertSee('100 meter total')->assertSee('Line discount: 50.00');
        $line = DocumentLine::withoutGlobalScopes()->where('business_document_id', $sale->id)->firstOrFail();
        $key = (string) Str::uuid();
        $this->post(route('store.post', [$this->store, 'return']), ['request_key' => $key, 'cash_session_id' => $this->session->id, 'original_id' => $sale->id, 'method' => 'cash', 'notes' => 'Synthetic return', 'lines' => [['source_line_id' => $line->id, 'quantity' => '10', 'disposition' => 'sellable']]])->assertSessionHasNoErrors();
        $return = BusinessDocument::withoutGlobalScopes()->where('request_key', $key)->firstOrFail();
        $this->get(route('store.receipt', [$this->store, $return->id, 'paper' => 80]))->assertOk()->assertSee('RETURN RECORD')->assertSee('Correction of')->assertSee('120.00');
    }

    public function test_receipt_access_is_store_scoped_and_requires_authentication(): void
    {
        $sale = $this->sale();
        $other = Store::factory()->create();
        $this->owner->memberships()->create(['store_id' => $other->id, 'role' => 'owner', 'status' => 'active']);
        $this->get(route('store.receipt', [$other, $sale->id]))->assertNotFound();
        $this->post(route('logout'))->assertRedirect();
        $this->get(route('store.receipt', [$this->store, $sale->id]))->assertRedirect(route('login'));
    }
}
