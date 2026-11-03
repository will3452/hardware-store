<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Store;
use App\Models\StoreModule;
use App\Models\User;
use App\Services\Operations;
use App\Services\Reports;
use App\Services\StoreAccess;
use App\Support\Tenancy\StoreContext;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExistingStoreUpgradeTest extends TestCase
{
    use DatabaseMigrations;

    public function test_additive_upgrade_preserves_existing_accounts_documents_stock_cash_and_retry_keys(): void
    {
        $store = Store::factory()->create();
        $owner = User::factory()->create();
        $owner->memberships()->create(['store_id' => $store->id, 'role' => 'owner', 'status' => 'active']);
        app(StoreContext::class)->set($store);
        foreach (array_diff(StoreAccess::MODULES, ['customers', 'credit', 'orders']) as $module) {
            StoreModule::create(['module_key' => $module, 'mode' => 'enabled']);
        }
        $product = Product::create(['sku' => 'EXISTING', 'name' => 'Existing custom item', 'base_unit' => 'legacy unit', 'fractional' => true, 'price_minor' => 1500]);
        $unit = ProductUnit::create(['product_id' => $product->id, 'label' => 'legacy unit', 'factor_micro' => 1000000]);
        $session = CashSession::create(['user_id' => $owner->id, 'request_key' => (string) Str::uuid(), 'opening_minor' => 50000, 'expected_minor' => 50000, 'opened_at' => now()]);
        $operations = app(Operations::class);
        $operations->post($owner, 'adjustment', ['request_key' => (string) Str::uuid(), 'product_id' => $product->id, 'mode' => 'opening', 'quantity' => '10', 'unit_cost' => '10', 'stock_version' => 0, 'notes' => 'Existing stock']);
        $data = ['request_key' => (string) Str::uuid(), 'cash_session_id' => $session->id, 'lines' => [['product_id' => $product->id, 'unit_id' => $unit->id, 'quantity' => '1.5', 'price' => '15', 'discount' => '0', 'discount_type' => 'amount']], 'payments' => [['method' => 'cash', 'amount' => '25']]];
        $sale = $operations->post($owner, 'sale', $data);
        $beforeMetrics = app(Reports::class)->summarize([$store->id], now()->subDay()->toDateString(), now()->addDay()->toDateString());
        $paths = ['2026_11_03_180000_add_product_templates.php', '2026_11_03_180100_add_customer_accounts.php', '2026_11_03_180200_add_sales_orders.php'];
        $migrations = array_map(fn ($path) => require database_path('migrations/'.$path), $paths);
        foreach (array_reverse($migrations) as $migration) {
            $migration->down();
        }
        $tables = ['users', 'stores', 'store_memberships', 'store_modules', 'products', 'product_units', 'business_documents', 'document_lines', 'payments', 'inventory_movements', 'cash_sessions', 'cash_movements'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }
        foreach ($migrations as $migration) {
            $migration->up();
        }
        foreach ($tables as $table) {
            $query = DB::table($table)->orderBy('id');
            if ($table === 'store_modules') {
                $query->whereNotIn('module_key', ['customers', 'credit', 'orders']);
            }
            if ($before[$table]) {
                $query->select(array_keys($before[$table][0]));
            }
            $this->assertSame($before[$table], $query->get()->map(fn ($row) => (array) $row)->all(), $table.' was changed during the upgrade');
        }
        $this->assertSame(3, DB::table('store_modules')->where('store_id', $store->id)->whereIn('module_key', ['customers', 'credit', 'orders'])->where('mode', 'disabled')->count());
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('receivable_entries', 0);
        $this->assertDatabaseCount('sales_orders', 0);
        $this->assertSame($beforeMetrics, app(Reports::class)->summarize([$store->id], now()->subDay()->toDateString(), now()->addDay()->toDateString()));
        $this->assertSame($sale->id, $operations->post($owner, 'sale', $data)->id);
        app(StoreContext::class)->clear();
        $this->actingAs($owner);
        foreach (['overview', 'products', 'inventory', 'sales', 'cash', 'reports'] as $page) {
            $this->get(route('store.page', [$store, $page]))->assertOk();
        }
    }
}
