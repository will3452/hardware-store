<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\BusinessDocument;
use App\Models\DocumentLine;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Store;
use App\Models\StoreModule;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Reports;
use App\Services\StoreAccess;
use App\Support\Tenancy\StoreContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreOperationsTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private User $owner;

    private Product $product;

    private ProductUnit $unit;

    private string $sessionId;

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
        $this->product = Product::create(['sku' => 'HAMMER', 'name' => 'Claw hammer', 'base_unit' => 'piece', 'price_minor' => 1500]);
        $this->unit = ProductUnit::create(['product_id' => $this->product->id, 'label' => 'piece', 'factor_micro' => 1000000]);
        app(StoreContext::class)->clear();
        $this->actingAs($this->owner);
        $this->post(route('store.cash', [$this->store, 'open']), ['request_key' => (string) Str::uuid(), 'amount' => '500'])->assertSessionHasNoErrors();
        $this->sessionId = DB::table('cash_sessions')->where('store_id', $this->store->id)->value('id');
        $this->postDocument('adjustment', ['product_id' => $this->product->id, 'mode' => 'opening', 'stock_version' => 0, 'quantity' => '200', 'unit_cost' => '10', 'notes' => 'Reviewed opening stock']);
    }

    /** @param array<string, mixed> $input */
    private function postDocument(string $kind, array $input): BusinessDocument
    {
        $key = $input['request_key'] ?? (string) Str::uuid();
        $this->post(route('store.post', [$this->store, $kind]), $input + ['request_key' => $key, 'cash_session_id' => $this->sessionId])->assertSessionHasNoErrors()->assertRedirect();

        return BusinessDocument::withoutGlobalScopes()->where('request_key', $key)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function saleData(string $quantity = '3', string $price = '15', string $cash = '100'): array
    {
        return ['request_key' => (string) Str::uuid(), 'cash_session_id' => $this->sessionId,
            'lines' => [['product_id' => $this->product->id, 'unit_id' => $this->unit->id, 'quantity' => $quantity, 'price' => $price, 'discount' => '0', 'discount_type' => 'amount']],
            'payments' => [['method' => 'cash', 'amount' => $cash]],
        ];
    }

    private function admin(): User
    {
        $admin = User::factory()->withTwoFactor()->create();
        foreach (StoreAccess::PERMISSIONS as $permission) {
            $admin->platformPermissions()->create(['permission' => $permission]);
        }
        $this->actingAs($admin)->withSession(['platform.mfa' => ['user_id' => $admin->id, 'fingerprint' => hash('sha256', $admin->two_factor_secret)]]);

        return $admin;
    }

    public function test_all_store_and_admin_screens_render(): void
    {
        foreach (['overview', 'products', 'inventory', 'purchasing', 'sales', 'cash', 'expenses', 'reports', 'audit', 'profile'] as $page) {
            $this->get(route('store.page', [$this->store, $page]))->assertOk();
        }
        $this->get(route('store.catalog', $this->store))->assertOk()->assertJsonFragment(['name' => 'Claw hammer']);
        $this->admin();
        $this->get('/admin')->assertOk();
        $this->get(route('admin.stores.show', $this->store))->assertOk();
        $this->get(route('admin.announcements'))->assertOk();
        $this->get(route('admin.audit'))->assertOk();
    }

    public function test_sale_posts_once_and_reconciles_stock_cash_and_reports(): void
    {
        $data = $this->saleData();
        $sale = $this->postDocument('sale', $data);
        $this->postDocument('sale', $data);
        $this->assertSame(1, BusinessDocument::withoutGlobalScopes()->where('kind', 'sale')->count());
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'quantity_micro' => 197000000, 'value_micro' => 1970000000]);
        $this->assertDatabaseHas('cash_sessions', ['id' => $this->sessionId, 'expected_minor' => 54500]);
        $this->assertEquals(5500, $sale->metadata['change_minor']);
        $metrics = app(Reports::class)->summarize([$this->store->id], now()->subDay()->toDateString(), now()->addDay()->toDateString());
        $this->assertSame(4500, $metrics['net_sales']);
        $this->assertSame(1500, $metrics['gross_profit']);
        $this->get(route('store.document', [$this->store, $sale]))->assertOk()->assertSee('Internal sales record');
        $data['lines'][0]['quantity'] = '4';
        $this->post(route('store.post', [$this->store, 'sale']), $data)->assertSessionHasErrors();
        $this->assertLedger();
    }

    public function test_underpayment_overselling_and_stale_session_roll_back_everything(): void
    {
        foreach ([$this->saleData('201', '1', '201'), $this->saleData('1', '15', '1'), array_replace($this->saleData(), ['cash_session_id' => (string) Str::ulid()])] as $input) {
            $this->post(route('store.post', [$this->store, 'sale']), $input)->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('business_documents', 1);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'quantity_micro' => 200000000]);
    }

    public function test_split_payment_applies_only_cash_after_change(): void
    {
        $data = $this->saleData('1', '1000', '500');
        $data['payments'][] = ['method' => 'ewallet', 'amount' => '600', 'reference' => 'CONFIRMED-123', 'confirmed' => '1'];
        $sale = $this->postDocument('sale', $data);
        $this->assertEquals(10000, $sale->metadata['change_minor']);
        $this->assertDatabaseHas('cash_sessions', ['id' => $this->sessionId, 'expected_minor' => 90000]);
        $this->assertDatabaseHas('payments', ['business_document_id' => $sale->id, 'method' => 'cash', 'amount_minor' => 40000]);
        $data['request_key'] = (string) Str::uuid();
        unset($data['payments'][1]['confirmed']);
        $this->post(route('store.post', [$this->store, 'sale']), $data)->assertSessionHasErrors();
    }

    public function test_partial_returns_allocate_rounding_and_damaged_goods_correctly(): void
    {
        $data = $this->saleData('3', '0.34', '2');
        $data['lines'][0]['discount'] = '0.02';
        $sale = $this->postDocument('sale', $data);
        $line = DocumentLine::withoutGlobalScopes()->where('business_document_id', $sale->id)->firstOrFail();
        $returnData = ['original_id' => $sale->id, 'method' => 'cash', 'lines' => [['source_line_id' => $line->id, 'quantity' => '1', 'disposition' => 'sellable']]];
        $first = $this->postDocument('return', $returnData);
        $this->assertEquals($first->gross_minor - $first->discount_minor, $first->total_minor);
        $returnData['lines'][0]['disposition'] = 'damaged';
        $this->postDocument('return', $returnData);
        $this->postDocument('return', $returnData);
        $this->assertEquals(100, BusinessDocument::withoutGlobalScopes()->where('kind', 'return')->sum('total_minor'));
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'quantity_micro' => 198000000, 'value_micro' => 1980000000]);
        $this->post(route('store.post', [$this->store, 'return']), $returnData + ['request_key' => (string) Str::uuid(), 'cash_session_id' => $this->sessionId])->assertSessionHasErrors();
        $metrics = app(Reports::class)->summarize([$this->store->id], now()->subDay()->toDateString(), now()->addDay()->toDateString());
        $this->assertSame(0, $metrics['net_sales']);
        $this->assertSame(0, $metrics['cost_of_goods']);
        $this->assertSame(2000, $metrics['inventory_losses']);
        $this->assertLedger();
    }

    public function test_cash_expense_reversal_and_close_variance(): void
    {
        $expense = $this->postDocument('expense', ['amount' => '100', 'method' => 'cash', 'category' => 'utilities', 'receipt_date' => now()->toDateString(), 'notes' => 'Electricity']);
        $this->assertDatabaseHas('cash_sessions', ['id' => $this->sessionId, 'expected_minor' => 40000]);
        $this->postDocument('expense_reversal', ['original_id' => $expense->id, 'notes' => 'Wrong amount, cash recovered']);
        $this->assertDatabaseHas('cash_sessions', ['id' => $this->sessionId, 'expected_minor' => 50000]);
        $close = ['request_key' => (string) Str::uuid(), 'session_id' => $this->sessionId, 'amount' => '490'];
        $this->post(route('store.cash', [$this->store, 'close']), $close)->assertSessionHasErrors();
        $this->post(route('store.cash', [$this->store, 'close']), $close + ['notes' => 'Counted shortage'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cash_sessions', ['id' => $this->sessionId, 'variance_minor' => -1000]);
        $this->post(route('store.post', [$this->store, 'sale']), $this->saleData())->assertSessionHasErrors();
    }

    public function test_counts_detect_intervening_sales_and_no_negative_cash(): void
    {
        $this->postDocument('sale', $this->saleData());
        $this->post(route('store.post', [$this->store, 'adjustment']), ['request_key' => (string) Str::uuid(), 'product_id' => $this->product->id, 'mode' => 'count', 'quantity' => '200', 'stock_version' => 1, 'unit_cost' => '10', 'notes' => 'Old count'])->assertSessionHasErrors();
        $this->post(route('store.cash', [$this->store, 'move']), ['request_key' => (string) Str::uuid(), 'session_id' => $this->sessionId, 'amount' => '1000', 'direction' => 'out', 'notes' => 'Withdrawal'])->assertSessionHasErrors();
        $this->assertDatabaseHas('cash_sessions', ['id' => $this->sessionId, 'expected_minor' => 54500]);
    }

    public function test_modules_reject_stale_writes_but_allow_restricted_returns(): void
    {
        $sale = $this->postDocument('sale', $this->saleData());
        DB::table('store_modules')->where('store_id', $this->store->id)->where('module_key', 'sales')->update(['mode' => 'restricted']);
        $this->post(route('store.post', [$this->store, 'sale']), $this->saleData())->assertForbidden();
        $line = DocumentLine::withoutGlobalScopes()->where('business_document_id', $sale->id)->firstOrFail();
        $this->postDocument('return', ['original_id' => $sale->id, 'method' => 'cash', 'lines' => [['source_line_id' => $line->id, 'quantity' => '1', 'disposition' => 'sellable']]]);
        DB::table('store_modules')->where('store_id', $this->store->id)->where('module_key', 'reports')->update(['mode' => 'disabled']);
        $this->get(route('store.page', [$this->store, 'reports']))->assertForbidden();
        $this->get(route('store.document', [$this->store, $sale]))->assertOk();
    }

    public function test_tenant_isolation_protects_documents_products_and_reports(): void
    {
        $sale = $this->postDocument('sale', $this->saleData());
        $other = Store::factory()->create();
        $otherOwner = User::factory()->create();
        $otherOwner->memberships()->create(['store_id' => $other->id, 'role' => 'owner', 'status' => 'active']);
        $this->actingAs($otherOwner)->get(route('store.document', [$other, $sale]))->assertNotFound();
        $this->get(route('store.page', [$this->store, 'reports']))->assertNotFound();
        $this->post(route('store.post', [$this->store, 'sale']), $this->saleData())->assertNotFound();
        $this->get('/admin')->assertForbidden();
    }

    public function test_admin_onboarding_link_is_single_use_and_owner_chooses_password(): void
    {
        $this->admin();
        $response = $this->post(route('admin.stores.create'), ['code' => 'new-store', 'display_name' => 'New Store', 'address_line_1' => '123 Main', 'city' => 'Manila', 'name' => 'New Owner', 'email' => 'newowner@example.test', 'modules' => StoreAccess::MODULES]);
        $response->assertSessionHasNoErrors()->assertSessionHas('invitation_link');
        $link = session('invitation_link');
        $this->post('/logout');
        $this->get($link)->assertOk()->assertSee('New Store');
        $this->post($link, ['password' => 'New-Owner-Password-723!', 'password_confirmation' => 'New-Owner-Password-723!'])->assertRedirect(route('login'));
        $this->get($link)->assertNotFound();
        $this->assertDatabaseHas('stores', ['code' => 'new-store', 'status' => 'active']);
        $user = User::where('email', 'newowner@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('New-Owner-Password-723!', $user->password));
    }

    public function test_announcements_filter_audience_drafts_and_expiration(): void
    {
        foreach (['visible', 'draft', 'other', 'expired'] as $title) {
            Announcement::create(['title' => $title, 'body' => $title.' message', 'user_id' => $this->owner->id, 'store_ids' => $title === 'other' ? [(string) Str::ulid()] : null, 'publish_at' => $title === 'draft' ? null : now()->subDay(), 'expires_at' => $title === 'expired' ? now()->subMinute() : null]);
        }
        $this->get(route('store.dashboard', $this->store))->assertOk()->assertSee('visible message')->assertDontSee('draft message')->assertDontSee('other message')->assertDontSee('expired message');
    }

    public function test_purchase_partial_receipt_reversal_and_latest_movement_guard(): void
    {
        app(StoreContext::class)->set($this->store);
        $supplier = Supplier::create(['name' => 'Supplier']);
        $box = ProductUnit::create(['product_id' => $this->product->id, 'label' => 'box100', 'factor_micro' => 100000000]);
        app(StoreContext::class)->clear();
        $this->post(route('store.purchases.save', $this->store), ['request_key' => (string) Str::uuid(), 'supplier_id' => $supplier->id, 'lines' => [['product_id' => $this->product->id, 'unit_id' => $box->id, 'quantity' => '2', 'cost' => '1200']]])->assertSessionHasNoErrors();
        $order = DB::table('purchase_orders')->first();
        $line = DB::table('purchase_order_lines')->first();
        $this->get(route('store.order', [$this->store, $order->id]))->assertOk();
        $this->post(route('store.order.status', [$this->store, $order->id]), ['status' => 'placed', 'notes' => 'Ordered'])->assertSessionHasNoErrors();
        $receiptData = ['order_id' => $order->id, 'lines' => [['order_line_id' => $line->id, 'quantity' => '1']]];
        $receipt = $this->postDocument('receipt', $receiptData);
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'quantity_micro' => 300000000, 'value_micro' => 3200000000]);
        $this->postDocument('receipt_reversal', ['original_id' => $receipt->id, 'notes' => 'Correction']);
        $this->assertDatabaseHas('purchase_order_lines', ['id' => $line->id, 'received_micro' => 0]);
        $receipt = $this->postDocument('receipt', $receiptData);
        $this->postDocument('sale', $this->saleData());
        $this->post(route('store.post', [$this->store, 'receipt_reversal']), ['request_key' => (string) Str::uuid(), 'original_id' => $receipt->id, 'notes' => 'Late correction'])->assertSessionHasErrors();
        $this->assertLedger();
    }

    public function test_reports_csv_and_private_attachments_are_scoped(): void
    {
        Storage::fake('local');
        $sale = $this->postDocument('sale', $this->saleData());
        foreach (['1', 'products', 'inventory'] as $export) {
            $this->get(route('store.page', [$this->store, 'reports']).'?export='.$export)->assertOk()->assertDownload();
        }
        $this->post(route('store.attachments.upload', [$this->store, $sale]), ['attachment' => UploadedFile::fake()->create('delivery.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors();
        $attachment = DB::table('document_attachments')->first();
        $this->get(route('store.attachments.download', [$this->store, $attachment->id]))->assertOk()->assertDownload('delivery.pdf');
        $other = Store::factory()->create();
        $this->owner->memberships()->create(['store_id' => $other->id, 'role' => 'owner', 'status' => 'active']);
        $this->get(route('store.attachments.download', [$other, $attachment->id]))->assertNotFound();
        $this->post(route('store.attachments.upload', [$this->store, $sale]), ['attachment' => UploadedFile::fake()->create('script.html', 10, 'text/html')])->assertSessionHasErrors();
        $this->assertSame("'=SUM(A1)", Reports::csvCell('=SUM(A1)'));
    }

    public function test_existing_identity_invitation_preserves_password_and_requires_that_identity(): void
    {
        $existing = User::factory()->create(['email' => 'existing@example.test', 'password' => 'Existing-Secret-763!']);
        $this->admin();
        $data = ['code' => 'existing-store', 'display_name' => 'Existing Store', 'address_line_1' => '321 Main', 'city' => 'Manila', 'name' => 'Existing Owner', 'email' => $existing->email, 'modules' => StoreAccess::MODULES];
        $this->post(route('admin.stores.create'), $data)->assertSessionHasErrors();
        $this->assertDatabaseMissing('stores', ['code' => 'existing-store']);
        $this->post(route('admin.stores.create'), $data + ['existing_identity' => '1'])->assertSessionHasNoErrors();
        $link = session('invitation_link');
        $this->post($link, [])->assertForbidden();
        $this->actingAs($existing)->post($link, [])->assertSessionHasNoErrors()->assertRedirect(route('store.index'));
        $this->assertTrue(Hash::check('Existing-Secret-763!', $existing->fresh()->password));
    }

    public function test_admin_module_dependencies_and_suspension_are_enforced(): void
    {
        $this->admin();
        $modes = array_fill_keys(StoreAccess::MODULES, 'enabled');
        $modes['inventory'] = 'disabled';
        $this->post(route('admin.stores.modules', $this->store), ['modules' => $modes])->assertSessionHasErrors();
        $this->post(route('admin.stores.update', $this->store), ['display_name' => $this->store->display_name, 'address_line_1' => $this->store->address_line_1, 'city' => $this->store->city, 'status' => 'suspended', 'reason' => 'Access review'])->assertSessionHasNoErrors();
        $this->get('/admin')->assertOk();
        $this->actingAs($this->owner)->post(route('store.post', [$this->store, 'sale']), $this->saleData())->assertNotFound();
    }

    public function test_recovery_response_does_not_disclose_account_and_admin_can_issue_owner_link(): void
    {
        $this->admin();
        $this->post(route('admin.stores.recovery', $this->store), ['confirm' => '1'])->assertSessionHas('recovery_link');
        $this->post('/logout');
        Notification::fake();
        $this->post('/forgot-password', ['email' => $this->owner->email])->assertSessionHasNoErrors();
        $status = session('status');
        $this->post('/forgot-password', ['email' => 'nobody@example.test'])->assertSessionHasNoErrors()->assertSessionHas('status', $status);
    }

    public function test_fractional_stock_and_reporting_local_midnight(): void
    {
        $this->post(route('store.products.save', $this->store), ['sku' => 'WIRE', 'name' => 'Wire', 'base_unit' => 'meter', 'fractional' => '1', 'price' => '20.00', 'reorder' => '1'])->assertSessionHasNoErrors();
        $wire = DB::table('products')->where('sku', 'WIRE')->first();
        $this->postDocument('adjustment', ['product_id' => $wire->id, 'stock_version' => 0, 'mode' => 'opening', 'quantity' => '2.5', 'unit_cost' => '5.123456', 'notes' => 'Reviewed fraction']);
        $sale = $this->postDocument('sale', $this->saleData());
        DB::table('business_documents')->where('id', $sale->id)->update(['posted_at' => '2026-10-30 16:00:00']);
        $metrics = app(Reports::class)->summarize([$this->store->id], '2026-10-31', '2026-10-31', 'Asia/Manila');
        $this->assertSame(4500, $metrics['net_sales']);
        $before = app(Reports::class)->summarize([$this->store->id], '2026-10-30', '2026-10-30', 'Asia/Manila');
        $this->assertSame(0, $before['net_sales']);
        $this->assertDatabaseHas('products', ['id' => $wire->id, 'quantity_micro' => 2500000, 'value_micro' => 12808640]);
        $this->assertLedger();
    }

    public function test_suppliers_can_be_corrected_and_archived_without_rewriting_order_history(): void
    {
        $this->post(route('store.suppliers.save', $this->store), ['name' => 'Original Supplier', 'contact' => 'Office'])->assertSessionHasNoErrors();
        $supplier = DB::table('suppliers')->first();
        $data = ['request_key' => (string) Str::uuid(), 'supplier_id' => $supplier->id, 'lines' => [['product_id' => $this->product->id, 'unit_id' => $this->unit->id, 'quantity' => '2', 'cost' => '10']]];
        $this->post(route('store.purchases.save', $this->store), $data)->assertSessionHasNoErrors();
        $order = DB::table('purchase_orders')->first();
        $this->post(route('store.order.status', [$this->store, $order->id]), ['status' => 'placed', 'notes' => 'Reviewed'])->assertSessionHasNoErrors();
        $this->post(route('store.suppliers.save', $this->store), ['supplier_id' => $supplier->id, 'name' => 'Corrected Supplier', 'active' => '0'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id, 'supplier_name' => 'Original Supplier']);
        $this->post(route('store.purchases.save', $this->store), array_replace($data, ['request_key' => (string) Str::uuid()]))->assertSessionHasErrors();
        $this->post(route('store.suppliers.save', $this->store), ['name' => ' corrected   supplier '])->assertSessionHasErrors();
        $line = DB::table('purchase_order_lines')->first();
        $this->postDocument('receipt', ['order_id' => $order->id, 'lines' => [['order_line_id' => $line->id, 'quantity' => '2']]]);
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'quantity_micro' => 202000000]);
        $this->assertLedger();
    }

    public function test_draft_edits_are_atomic_retryable_and_reject_stale_or_placed_orders(): void
    {
        $this->post(route('store.suppliers.save', $this->store), ['name' => 'Draft Supplier'])->assertSessionHasNoErrors();
        $supplier = DB::table('suppliers')->first();
        $input = ['request_key' => (string) Str::uuid(), 'supplier_id' => $supplier->id, 'lines' => [['product_id' => $this->product->id, 'unit_id' => $this->unit->id, 'quantity' => '1', 'cost' => '10.123456']]];
        $this->post(route('store.purchases.save', $this->store), $input)->assertSessionHasNoErrors();
        $order = DB::table('purchase_orders')->first();
        $edit = array_replace($input, ['request_key' => (string) Str::uuid(), 'revision' => 0]);
        $edit['lines'][0]['quantity'] = '3';
        $url = route('store.purchases.update', [$this->store, $order->id]);
        $this->post($url, $edit)->assertSessionHasNoErrors();
        $this->post($url, $edit)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id, 'revision' => 1]);
        $this->assertDatabaseCount('purchase_order_lines', 1);
        $this->assertDatabaseHas('purchase_order_lines', ['purchase_order_id' => $order->id, 'quantity_micro' => 3000000, 'unit_cost_micro' => 10123456, 'total_cost_micro' => 30370368]);
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'quantity_micro' => 200000000]);
        $this->post($url, array_replace($edit, ['request_key' => (string) Str::uuid()]))->assertSessionHasErrors();
        $invalid = array_replace($edit, ['request_key' => (string) Str::uuid(), 'revision' => 1]);
        $invalid['lines'][0]['unit_id'] = (string) Str::ulid();
        $this->post($url, $invalid)->assertNotFound();
        $this->assertDatabaseHas('purchase_order_lines', ['purchase_order_id' => $order->id, 'quantity_micro' => 3000000]);
        $this->get(route('store.order', [$this->store, $order->id]))->assertOk()->assertSee('Edit draft order');
        $status = route('store.order.status', [$this->store, $order->id]);
        $this->post($status, ['status' => 'placed', 'revision' => 0, 'notes' => 'Old tab'])->assertSessionHasErrors();
        $this->post($status, ['status' => 'placed', 'revision' => 1, 'notes' => 'Reviewed current draft'])->assertSessionHasNoErrors();
        $this->post($url, array_replace($edit, ['request_key' => (string) Str::uuid(), 'revision' => 2]))->assertSessionHasErrors();
        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id, 'status' => 'placed']);
    }

    public function test_checkout_method_changes_reject_stale_sales_but_preserve_posted_retries_and_refunds(): void
    {
        $this->post(route('store.payments.settings', $this->store), ['checkout_methods' => ['card']])->assertSessionHasNoErrors();
        $this->post(route('store.payments.settings', $this->store), ['checkout_methods' => []])->assertSessionHasErrors();
        $this->post(route('store.post', [$this->store, 'sale']), $this->saleData())->assertSessionHasErrors();
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'quantity_micro' => 200000000]);
        $data = $this->saleData();
        $data['payments'] = [['method' => 'card', 'amount' => '45', 'reference' => 'CONFIRMED', 'confirmed' => '1']];
        $sale = $this->postDocument('sale', $data);
        $this->post(route('store.payments.settings', $this->store), ['checkout_methods' => ['cash']])->assertSessionHasNoErrors();
        $this->postDocument('sale', $data);
        $line = DB::table('document_lines')->where('business_document_id', $sale->id)->first();
        $this->postDocument('return', ['original_id' => $sale->id, 'method' => 'card', 'reference' => 'REFUNDED', 'confirmed' => '1', 'lines' => [['source_line_id' => $line->id, 'quantity' => '3', 'disposition' => 'sellable']]]);
        $this->assertDatabaseHas('cash_sessions', ['id' => $this->sessionId, 'expected_minor' => 50000]);
        $this->assertLedger();
    }

    public function test_stale_catalog_price_is_rejected_without_stock_or_payment_effects(): void
    {
        $data = $this->saleData();
        $data['lines'][0]['catalog_price_minor'] = 1500;
        DB::table('products')->where('id', $this->product->id)->update(['price_minor' => 2000]);
        $this->post(route('store.post', [$this->store, 'sale']), $data)->assertSessionHasErrors();
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'quantity_micro' => 200000000]);
        $data['lines'][0]['catalog_price_minor'] = 2000;
        $data['lines'][0]['price'] = '20';
        $sale = $this->postDocument('sale', $data);
        $this->assertSame(6000, $sale->total_minor);
    }

    public function test_history_filters_use_local_dates_and_cannot_expose_another_stores_records(): void
    {
        $sale = $this->postDocument('sale', $this->saleData() + ['customer' => 'Northside customer']);
        DB::table('business_documents')->where('id', $sale->id)->update(['posted_at' => '2026-11-01 16:00:00']);
        $url = route('store.page', [$this->store, 'sales']);
        $this->get($url.'?history_q=Northside&history_from=2026-11-02&history_to=2026-11-02')->assertOk()->assertSee(substr($sale->id, -10));
        $this->get($url.'?history_q=Northside&history_from=2026-11-01&history_to=2026-11-01')->assertOk()->assertDontSee(substr($sale->id, -10));
        $other = Store::factory()->create();
        app(StoreContext::class)->set($other);
        BusinessDocument::create(['kind' => 'sale', 'request_key' => (string) Str::uuid(), 'fingerprint' => str_repeat('a', 64), 'user_id' => $this->owner->id, 'metadata' => ['customer' => 'Private tenant customer'], 'posted_at' => now()]);
        app(StoreContext::class)->clear();
        $this->get($url.'?history_to=2026-11-02')->assertOk()->assertSee(substr($sale->id, -10));
        $this->get($url.'?history_q=Private')->assertOk()->assertDontSee('Private tenant customer');
        $this->get($url.'?history_kind=expense')->assertSessionHasErrors();
    }

    public function test_cash_session_detail_reconciles_closed_history_and_exports_safe_scoped_csv(): void
    {
        $this->post(route('store.cash', [$this->store, 'move']), ['request_key' => (string) Str::uuid(), 'session_id' => $this->sessionId, 'direction' => 'in', 'amount' => '10', 'notes' => '=SUM(1,1)'])->assertSessionHasNoErrors();
        $this->post(route('store.cash', [$this->store, 'close']), ['request_key' => (string) Str::uuid(), 'session_id' => $this->sessionId, 'amount' => '510'])->assertSessionHasNoErrors();
        $url = route('store.cash.show', [$this->store, $this->sessionId]);
        $this->get($url)->assertOk()->assertSee('510.00')->assertDontSee('does not match');
        $this->assertStringContainsString("'=SUM(1,1)", $this->get($url.'?export=1')->assertOk()->streamedContent());
        $this->assertSame(1, DB::table('audit_events')->where('action', 'cash.session_exported')->count());
        $other = Store::factory()->create();
        $this->owner->memberships()->create(['store_id' => $other->id, 'role' => 'owner', 'status' => 'active']);
        $this->get(route('store.cash.show', [$other, $this->sessionId]))->assertNotFound();
        $this->get(route('store.cash.show', [$other, $this->sessionId, 'export' => 1]))->assertNotFound();
    }

    public function test_system_status_is_admin_only_and_reports_missing_scheduler(): void
    {
        $this->get(route('admin.health'))->assertForbidden();
        $this->admin();
        $this->get(route('admin.health'))->assertOk()->assertSee('No scheduler heartbeat received')->assertSee('Email is not configured');
        Cache::put('scheduler:last_seen', now()->toIso8601String());
        $this->get(route('admin.health'))->assertOk()->assertSee('Last heartbeat:')->assertDontSee('No scheduler heartbeat received');
    }

    public function test_reversing_a_partial_receipt_does_not_reopen_a_closed_order(): void
    {
        $this->post(route('store.suppliers.save', $this->store), ['name' => 'Closed Order Supplier'])->assertSessionHasNoErrors();
        $supplier = DB::table('suppliers')->first();
        $this->post(route('store.purchases.save', $this->store), ['request_key' => (string) Str::uuid(), 'supplier_id' => $supplier->id, 'lines' => [['product_id' => $this->product->id, 'unit_id' => $this->unit->id, 'quantity' => '2', 'cost' => '10']]])->assertSessionHasNoErrors();
        $order = DB::table('purchase_orders')->first();
        $status = route('store.order.status', [$this->store, $order->id]);
        $this->post($status, ['status' => 'placed', 'notes' => 'Reviewed'])->assertSessionHasNoErrors();
        $line = DB::table('purchase_order_lines')->first();
        $receipt = $this->postDocument('receipt', ['order_id' => $order->id, 'lines' => [['order_line_id' => $line->id, 'quantity' => '1']]]);
        $this->post($status, ['status' => 'closed', 'notes' => 'Supplier cannot fulfill remainder'])->assertSessionHasNoErrors();
        $this->postDocument('receipt_reversal', ['original_id' => $receipt->id, 'notes' => 'Incorrect delivery']);
        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id, 'status' => 'closed']);
        $this->assertDatabaseHas('purchase_order_lines', ['id' => $line->id, 'received_micro' => 0]);
        $this->assertLedger();
    }

    public function test_stale_inventory_form_requires_a_recount_and_does_not_refresh_its_version(): void
    {
        $this->postDocument('sale', $this->saleData());
        $url = route('store.page', [$this->store, 'inventory']);
        $this->from($url)->post(route('store.post', [$this->store, 'adjustment']), ['_form' => 'stock-'.$this->product->id, 'request_key' => (string) Str::uuid(), 'product_id' => $this->product->id, 'stock_version' => 1, 'mode' => 'count', 'quantity' => '200', 'notes' => 'Old count'])->assertSessionHasErrors();
        $this->get($url)->assertOk()->assertSee('Reload to recount')->assertSee('name="stock_version" value="1"', false)->assertSee('disabled>Post stock adjustment', false);
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'quantity_micro' => 197000000, 'stock_version' => 2]);
    }

    private function assertLedger(): void
    {
        foreach (DB::table('products')->get() as $product) {
            $movements = DB::table('inventory_movements')->where('product_id', $product->id);
            $this->assertEquals($product->quantity_micro, $movements->sum('quantity_micro'));
            $this->assertEquals($product->value_micro, $movements->sum('value_micro'));
        }
        foreach (DB::table('cash_sessions')->get() as $session) {
            $this->assertEquals($session->expected_minor, $session->opening_minor + DB::table('cash_movements')->where('cash_session_id', $session->id)->sum('amount_minor'));
        }
    }
}
