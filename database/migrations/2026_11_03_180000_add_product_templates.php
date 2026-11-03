<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->string('template_key', 30)->nullable();
            $t->unsignedSmallInteger('template_version')->nullable();
            $t->json('specifications')->nullable();
            $t->string('template_fingerprint', 64)->nullable();
            $t->unique(['store_id', 'template_fingerprint'], 'products_template_unique');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->dropUnique('products_template_unique');
            $t->dropColumn(['template_key', 'template_version', 'specifications', 'template_fingerprint']);
        });
    }
};
