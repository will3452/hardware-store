<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_invitations', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('store_id')->constrained()->restrictOnDelete();
            $t->foreignUlid('user_id')->constrained()->restrictOnDelete();
            $t->string('token_hash', 64)->unique();
            $t->boolean('new_identity');
            $t->timestamp('expires_at');
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
        });
        Schema::create('announcements', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('title');
            $t->text('body');
            $t->json('store_ids')->nullable();
            $t->timestamp('publish_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('withdrawn_at')->nullable();
            $t->foreignUlid('user_id')->constrained()->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('store_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignUlid('user_id')->constrained()->restrictOnDelete();
            $t->string('action', 80);
            $t->string('subject_id', 40)->nullable();
            $t->json('details');
            $t->timestamp('created_at');
            $t->index(['store_id', 'created_at']);
        });
        Schema::create('products', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('sku', 80);
            $t->string('barcode', 100)->nullable();
            $t->string('name');
            $t->string('category', 80)->nullable();
            $t->string('brand', 80)->nullable();
            $t->string('base_unit', 30);
            $t->boolean('fractional')->default(false);
            $t->boolean('active')->default(true);
            $t->unsignedBigInteger('price_minor');
            $t->unsignedBigInteger('reorder_micro')->default(0);
            $t->unsignedBigInteger('quantity_micro')->default(0);
            $t->unsignedBigInteger('value_micro')->default(0);
            $t->unsignedBigInteger('stock_version')->default(0);
            $t->timestamps();
            $t->unique(['store_id', 'sku']);
            $t->unique(['store_id', 'barcode']);
        });
        Schema::create('product_units', function (Blueprint $t) {
            $this->tenant($t);
            $t->ulid('product_id');
            $this->owned($t, 'product_id', 'products');
            $t->string('label', 30);
            $t->unsignedBigInteger('factor_micro');
            $t->timestamps();
            $t->unique(['store_id', 'product_id', 'label']);
        });
        Schema::create('suppliers', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('name');
            $t->string('contact')->nullable();
            $t->text('address')->nullable();
            $t->timestamps();
        });
        Schema::create('purchase_orders', function (Blueprint $t) {
            $this->tenant($t);
            $t->ulid('supplier_id');
            $this->owned($t, 'supplier_id', 'suppliers');
            $t->string('supplier_name');
            $t->string('status', 20)->default('draft');
            $t->string('request_key', 36);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['store_id', 'request_key']);
        });
        Schema::create('purchase_order_lines', function (Blueprint $t) {
            $this->tenant($t);
            $t->ulid('purchase_order_id');
            $this->owned($t, 'purchase_order_id', 'purchase_orders');
            $t->ulid('product_id');
            $this->owned($t, 'product_id', 'products');
            $t->string('name');
            $t->string('sku', 80);
            $t->string('unit_label', 30);
            $t->unsignedBigInteger('factor_micro');
            $t->unsignedBigInteger('quantity_micro');
            $t->unsignedBigInteger('received_micro')->default(0);
            $t->unsignedBigInteger('total_cost_micro');
            $t->timestamps();
        });
        Schema::create('cash_sessions', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignUlid('user_id')->constrained()->restrictOnDelete();
            $t->string('request_key', 36);
            $t->unsignedBigInteger('opening_minor');
            $t->unsignedBigInteger('expected_minor');
            $t->unsignedBigInteger('counted_minor')->nullable();
            $t->bigInteger('variance_minor')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('opened_at');
            $t->timestamp('closed_at')->nullable();
            $t->unsignedTinyInteger('open_guard')->storedAs('CASE WHEN closed_at IS NULL THEN 1 ELSE NULL END');
            $t->timestamps();
            $t->unique(['store_id', 'open_guard']);
            $t->unique(['store_id', 'request_key']);
        });
        Schema::create('business_documents', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('kind', 30);
            $t->string('request_key', 36);
            $t->string('fingerprint', 64);
            $t->ulid('original_id')->nullable();
            $this->owned($t, 'original_id', 'business_documents');
            $t->foreignUlid('user_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('gross_minor')->default(0);
            $t->unsignedBigInteger('discount_minor')->default(0);
            $t->unsignedBigInteger('total_minor')->default(0);
            $t->unsignedBigInteger('cost_micro')->default(0);
            $t->unsignedBigInteger('loss_micro')->default(0);
            $t->unsignedBigInteger('gain_micro')->default(0);
            $t->json('metadata');
            $t->timestamp('posted_at');
            $t->timestamps();
            $t->unique(['store_id', 'request_key']);
            $t->index(['store_id', 'kind', 'posted_at']);
        });
        Schema::create('document_lines', function (Blueprint $t) {
            $this->tenant($t);
            $t->ulid('business_document_id');
            $this->owned($t, 'business_document_id', 'business_documents');
            $t->ulid('product_id');
            $this->owned($t, 'product_id', 'products');
            $t->ulid('source_line_id')->nullable();
            $this->owned($t, 'source_line_id', 'document_lines');
            $t->ulid('purchase_order_line_id')->nullable();
            $this->owned($t, 'purchase_order_line_id', 'purchase_order_lines');
            $t->string('name');
            $t->string('sku', 80);
            $t->string('base_unit', 30);
            $t->string('unit_label', 30);
            $t->unsignedBigInteger('factor_micro');
            $t->unsignedBigInteger('quantity_micro');
            $t->unsignedBigInteger('price_minor')->default(0);
            $t->unsignedBigInteger('gross_minor')->default(0);
            $t->unsignedBigInteger('discount_minor')->default(0);
            $t->unsignedBigInteger('total_minor')->default(0);
            $t->unsignedBigInteger('cost_micro')->default(0);
            $t->string('disposition', 20)->nullable();
            $t->timestamps();
        });
        Schema::create('inventory_movements', function (Blueprint $t) {
            $this->tenant($t);
            $t->ulid('product_id');
            $this->owned($t, 'product_id', 'products');
            $t->ulid('business_document_id');
            $this->owned($t, 'business_document_id', 'business_documents');
            $t->bigInteger('quantity_micro');
            $t->bigInteger('value_micro');
            $t->unsignedBigInteger('stock_version');
            $t->string('reason', 100);
            $t->timestamp('created_at');
            $t->index(['store_id', 'product_id', 'created_at']);
        });
        Schema::create('payments', function (Blueprint $t) {
            $this->tenant($t);
            $t->ulid('business_document_id');
            $this->owned($t, 'business_document_id', 'business_documents');
            $t->string('method', 20);
            $t->unsignedBigInteger('amount_minor');
            $t->string('reference')->nullable();
            $t->timestamps();
        });
        Schema::create('cash_movements', function (Blueprint $t) {
            $this->tenant($t);
            $t->ulid('cash_session_id');
            $this->owned($t, 'cash_session_id', 'cash_sessions');
            $t->ulid('business_document_id')->nullable();
            $this->owned($t, 'business_document_id', 'business_documents');
            $t->string('request_key', 80);
            $t->bigInteger('amount_minor');
            $t->string('reason');
            $t->timestamp('created_at');
            $t->unique(['store_id', 'request_key']);
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
        foreach (['cash_movements', 'payments', 'inventory_movements', 'document_lines', 'business_documents', 'cash_sessions', 'purchase_order_lines', 'purchase_orders', 'suppliers', 'product_units', 'products', 'audit_events', 'announcements', 'store_invitations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
