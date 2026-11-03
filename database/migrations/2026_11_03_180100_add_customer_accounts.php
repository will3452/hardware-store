<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('name');
            $t->string('contact')->nullable();
            $t->text('address')->nullable();
            $t->boolean('active')->default(true);
            $t->boolean('credit_enabled')->default(false);
            $t->unsignedBigInteger('credit_limit_minor')->default(0);
            $t->unsignedSmallInteger('term_days')->default(30);
            $t->unsignedInteger('revision')->default(1);
            $t->timestamps();
        });
        Schema::table('business_documents', function (Blueprint $t) {
            $t->ulid('customer_id')->nullable();
            $this->owned($t, 'customer_id', 'customers');
            $t->date('due_date')->nullable();
        });
        Schema::create('receivable_entries', function (Blueprint $t) {
            $this->tenant($t);
            $t->ulid('customer_id');
            $this->owned($t, 'customer_id', 'customers');
            $t->ulid('business_document_id');
            $this->owned($t, 'business_document_id', 'business_documents');
            $t->ulid('obligation_id');
            $this->owned($t, 'obligation_id', 'business_documents');
            $t->string('kind', 25);
            $t->bigInteger('amount_minor');
            $t->timestamp('posted_at');
            $t->unique(['store_id', 'business_document_id', 'obligation_id', 'kind'], 'receivable_source_unique');
            $t->index(['store_id', 'customer_id', 'posted_at'], 'receivable_customer_date');
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
        Schema::dropIfExists('receivable_entries');
        Schema::table('business_documents', function (Blueprint $t) {
            $t->dropForeign(['store_id', 'customer_id']);
            $t->dropColumn(['customer_id', 'due_date']);
        });
        Schema::dropIfExists('customers');
    }
};
