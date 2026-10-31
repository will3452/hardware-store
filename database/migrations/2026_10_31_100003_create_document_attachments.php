<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_attachments', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('store_id')->constrained()->restrictOnDelete();
            $t->ulid('business_document_id');
            $t->foreign(['store_id', 'business_document_id'])->references(['store_id', 'id'])->on('business_documents')->restrictOnDelete();
            $t->foreignUlid('user_id')->constrained()->restrictOnDelete();
            $t->string('path');
            $t->string('name');
            $t->string('mime', 100);
            $t->unsignedInteger('size');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_attachments');
    }
};
