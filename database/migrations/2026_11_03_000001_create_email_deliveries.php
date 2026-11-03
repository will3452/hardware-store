<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_deliveries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('kind', 40);
            $table->string('recipient');
            $table->ulid('source_id')->nullable();
            $table->string('dedupe_key')->unique();
            $table->longText('payload')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at');
            $table->timestamp('lease_until')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('sent_at')->nullable();
            $table->string('last_error', 80)->nullable();
            $table->timestamps();
            $table->index(['status', 'available_at']);
        });
        Schema::table('announcements', function (Blueprint $table): void {
            $table->boolean('email_owners')->default(false);
            $table->timestamp('email_prepared_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('announcements', fn (Blueprint $table) => $table->dropColumn(['email_owners', 'email_prepared_at']));
        Schema::dropIfExists('email_deliveries');
    }
};
