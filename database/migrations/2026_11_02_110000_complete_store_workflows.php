<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', fn (Blueprint $t) => $t->json('checkout_methods')->nullable());
        Schema::table('suppliers', fn (Blueprint $t) => $t->boolean('active')->default(true));
        Schema::table('purchase_orders', function (Blueprint $t) {
            $t->unsignedInteger('revision')->default(0);
            $t->uuid('last_edit_key')->nullable();
            $t->string('last_edit_fingerprint', 64)->nullable();
            $t->unique(['store_id', 'last_edit_key']);
        });
        Schema::table('purchase_order_lines', fn (Blueprint $t) => $t->unsignedBigInteger('unit_cost_micro')->nullable());
    }

    public function down(): void
    {
        Schema::table('purchase_order_lines', fn (Blueprint $t) => $t->dropColumn('unit_cost_micro'));
        Schema::table('purchase_orders', function (Blueprint $t) {
            $t->dropUnique(['store_id', 'last_edit_key']);
            $t->dropColumn(['revision', 'last_edit_key', 'last_edit_fingerprint']);
        });
        Schema::table('suppliers', fn (Blueprint $t) => $t->dropColumn('active'));
        Schema::table('stores', fn (Blueprint $t) => $t->dropColumn('checkout_methods'));
    }
};
