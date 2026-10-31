<?php

namespace Tests\Feature;

use App\Models\BusinessDocument;
use App\Models\CashSession;
use App\Models\DocumentLine;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Store;
use App\Models\StoreModule;
use App\Models\User;
use App\Services\Operations;
use App\Services\StoreAccess;
use App\Support\Tenancy\StoreContext;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PostingConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_parallel_sales_and_retries_cannot_oversell_or_double_post(): void
    {
        [$store, $user, $product, $unit, $session] = $this->fixture();
        $data = ['request_key' => (string) Str::uuid(), 'cash_session_id' => $session->id, 'lines' => [['product_id' => $product->id, 'unit_id' => $unit->id, 'quantity' => '1', 'price' => '10', 'discount' => '0', 'discount_type' => 'amount']], 'payments' => [['method' => 'cash', 'amount' => '10']]];
        $results = $this->parallel($store, $user, 'sale', [$data, $data]);
        $this->assertSame('posted', $results[0]['result']);
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertSame(1, BusinessDocument::where('kind', 'sale')->count());
        $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity_micro' => 1000000]);
        $data['request_key'] = (string) Str::uuid();
        $other = $data;
        $other['request_key'] = (string) Str::uuid();
        $results = $this->parallel($store, $user, 'sale', [$data, $other]);
        $this->assertSame(['posted', 'rejected'], collect($results)->pluck('result')->sort()->values()->all());
        $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity_micro' => 0, 'value_micro' => 0]);
        $this->assertDatabaseHas('cash_sessions', ['id' => $session->id, 'expected_minor' => 12000]);
    }

    public function test_parallel_returns_cannot_refund_the_same_quantity_twice(): void
    {
        [$store, $user, $product, $unit, $session] = $this->fixture();
        $sale = app(Operations::class)->post($user, 'sale', ['request_key' => (string) Str::uuid(), 'cash_session_id' => $session->id, 'lines' => [['product_id' => $product->id, 'unit_id' => $unit->id, 'quantity' => '1', 'price' => '10', 'discount' => '0', 'discount_type' => 'amount']], 'payments' => [['method' => 'cash', 'amount' => '10']]]);
        $line = DocumentLine::where('business_document_id', $sale->id)->firstOrFail();
        $data = ['request_key' => (string) Str::uuid(), 'cash_session_id' => $session->id, 'original_id' => $sale->id, 'method' => 'cash', 'lines' => [['source_line_id' => $line->id, 'quantity' => '1', 'disposition' => 'sellable']]];
        $other = $data;
        $other['request_key'] = (string) Str::uuid();
        $results = $this->parallel($store, $user, 'return', [$data, $other]);
        $this->assertSame(['posted', 'rejected'], collect($results)->pluck('result')->sort()->values()->all());
        $this->assertSame(1, BusinessDocument::where('kind', 'return')->count());
        $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity_micro' => 2000000]);
        $this->assertDatabaseHas('cash_sessions', ['id' => $session->id, 'expected_minor' => 10000]);
    }

    private function fixture(): array
    {
        $store = Store::factory()->create();
        $user = User::factory()->create();
        $user->memberships()->create(['store_id' => $store->id, 'role' => 'owner', 'status' => 'active']);
        app(StoreContext::class)->set($store);
        foreach (StoreAccess::MODULES as $key) {
            StoreModule::create(['module_key' => $key, 'mode' => 'enabled']);
        }
        $product = Product::create(['sku' => 'LAST-UNIT', 'name' => 'Concurrent fixture', 'base_unit' => 'piece', 'price_minor' => 1000]);
        $unit = ProductUnit::create(['product_id' => $product->id, 'label' => 'piece', 'factor_micro' => 1000000]);
        $session = CashSession::create(['user_id' => $user->id, 'request_key' => (string) Str::uuid(), 'opening_minor' => 10000, 'expected_minor' => 10000, 'opened_at' => now()]);
        app(Operations::class)->post($user, 'adjustment', ['request_key' => (string) Str::uuid(), 'product_id' => $product->id, 'mode' => 'opening', 'stock_version' => 0, 'quantity' => '2', 'unit_cost' => '5', 'notes' => 'Concurrency fixture']);

        return [$store, $user, $product, $unit, $session];
    }

    private function parallel(Store $store, User $user, string $kind, array $inputs): array
    {
        $processes = [];
        $paths = [];
        $db = config('database.connections.mysql');
        try {
            // Hold the common lock so both workers contend for the same transaction boundary.
            DB::beginTransaction();
            DB::table('stores')->where('id', $store->id)->lockForUpdate()->first();
            foreach ($inputs as $data) {
                $path = tempnam(sys_get_temp_dir(), 'hms-concurrency-');
                $paths[] = $path;
                file_put_contents($path, json_encode(['store_id' => $store->id, 'user_id' => $user->id, 'kind' => $kind, 'data' => $data], JSON_THROW_ON_ERROR));
                $process = new Process([PHP_BINARY, base_path('tests/Support/post-worker.php'), $path], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'hardware_store_test', 'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'], 'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password'], 'DB_CONNECTION' => 'mysql']);
                $process->start();
                $processes[] = $process;
            }
            usleep(250000);
            DB::commit();
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
                $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            foreach ($paths as $path) {
                unlink($path);
            }
        }
    }
}
