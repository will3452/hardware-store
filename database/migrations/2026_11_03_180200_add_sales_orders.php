<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $t) {
            $this->tenant($t);
            $t->ulid('customer_id');
            $this->owned($t, 'customer_id', 'customers');
            $t->ulid('replaces_id')->nullable();
            $t->string('status', 20)->default('draft');
            $t->unsignedInteger('revision')->default(1);
            $t->unsignedInteger('quote_version')->default(1);
            $t->json('store_snapshot')->nullable();
            $t->string('customer_name');
            $t->text('customer_address')->nullable();
            $t->date('valid_until');
            $t->string('project')->nullable();
            $t->text('notes')->nullable();
            $t->text('acceptance_note')->nullable();
            $t->text('close_reason')->nullable();
            $t->unsignedBigInteger('total_minor')->default(0);
            $t->timestamp('issued_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamps();
            $t->unique(['store_id', 'replaces_id']);
        });
        Schema::table('sales_orders', fn (Blueprint $t) => $this->owned($t, 'replaces_id', 'sales_orders'));
        Schema::create('sales_order_lines', function (Blueprint $t) {
            $this->tenant($t);
            $t->ulid('sales_order_id');
            $this->owned($t, 'sales_order_id', 'sales_orders');
            $t->ulid('product_id');
            $this->owned($t, 'product_id', 'products');
            $t->ulid('product_unit_id');
            $this->owned($t, 'product_unit_id', 'product_units');
            $t->string('name');
            $t->string('sku', 80);
            $t->string('base_unit', 30);
            $t->string('unit_label', 30);
            $t->unsignedBigInteger('factor_micro');
            $t->unsignedBigInteger('quantity_micro');
            $t->unsignedBigInteger('released_micro')->default(0);
            $t->unsignedBigInteger('cancelled_micro')->default(0);
            $t->unsignedBigInteger('price_minor');
            $t->unsignedBigInteger('gross_minor');
            $t->unsignedBigInteger('discount_minor');
            $t->unsignedBigInteger('total_minor');
            $t->unsignedBigInteger('released_total_minor')->default(0);
            $t->unsignedBigInteger('released_discount_minor')->default(0);
        });
        Schema::create('order_releases', function (Blueprint $t) {
            $this->tenant($t);
            $t->ulid('sales_order_id');
            $this->owned($t, 'sales_order_id', 'sales_orders');
            $t->ulid('business_document_id');
            $this->owned($t, 'business_document_id', 'business_documents');
            $t->string('method', 20);
            $t->string('recipient');
            $t->text('address')->nullable();
            $t->text('reference')->nullable();
            $t->timestamp('released_at');
            $t->timestamp('received_at')->nullable();
            $t->string('received_by')->nullable();
            $t->unique(['store_id', 'business_document_id']);
        });
        Schema::create('sales_order_actions', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('request_key', 36);
            $t->string('fingerprint', 64);
            $t->ulid('sales_order_id');
            $this->owned($t, 'sales_order_id', 'sales_orders');
            $t->string('action', 30);
            $t->timestamp('created_at');
            $t->unique(['store_id', 'request_key']);
        });
        // Existing businesses opt into the new workflows explicitly. No merchant data is transformed.
        DB::table('stores')->orderBy('id')->chunk(200, function ($stores): void {
            foreach ($stores as $store) {
                foreach (['customers', 'credit', 'orders'] as $module) {
                    DB::table('store_modules')->insertOrIgnore(['id' => (string) Str::ulid(), 'store_id' => $store->id, 'module_key' => $module, 'mode' => 'disabled', 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        });
    }

    private function tenant(Blueprint $t): void
    {
        $t->ulid('id')->primary();
        $t->foreignUlid('store_id')->constrained()->restrictOnDelete();
        $t->unique(['store_id', 'id']);
    }

    private function owned(Blueprint $t, string $column, string $table): void
    {
        $t->foreign(['store_id', $column])->references(['store_id', 'id'])->on($table)->restrictOnDelete();
    }

    public function down(): void
    {
        foreach (['sales_order_actions', 'order_releases', 'sales_order_lines'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('sales_orders', fn (Blueprint $t) => $t->dropForeign(['store_id', 'replaces_id']));
        Schema::dropIfExists('sales_orders');
        DB::table('store_modules')->whereIn('module_key', ['customers', 'credit', 'orders'])->delete();
    }
};
