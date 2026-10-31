<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('display_name');
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city');
            $table->string('province')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->char('country_code', 2)->default('PH');
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->char('currency', 3)->default('PHP');
            $table->string('timezone')->default('Asia/Manila');
            $table->string('status', 30)->default('pending_activation');
            $table->unsignedBigInteger('authorization_version')->default(1);
            $table->timestamps();
        });

        Schema::create('platform_user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('user_id')->constrained()->restrictOnDelete();
            $table->string('permission', 100);
            $table->timestamps();
            $table->unique(['user_id', 'permission']);
        });

        Schema::create('store_memberships', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('user_id')->constrained()->restrictOnDelete();
            $table->string('role', 30)->default('owner');
            $table->string('status', 30)->default('pending');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedTinyInteger('active_owner_guard')
                ->storedAs("CASE WHEN status = 'active' AND role = 'owner' THEN 1 ELSE NULL END");
            $table->unsignedTinyInteger('current_user_guard')
                ->storedAs("CASE WHEN status IN ('pending', 'active') THEN 1 ELSE NULL END");
            $table->timestamps();
            $table->unique(['store_id', 'active_owner_guard'], 'membership_active_owner_unique');
            $table->unique(['store_id', 'user_id', 'current_user_guard'], 'membership_current_user_unique');
            $table->index(['user_id', 'status']);
        });

        Schema::create('store_modules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained()->restrictOnDelete();
            $table->string('module_key', 40);
            $table->string('mode', 20)->default('enabled');
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['store_id', 'module_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_modules');
        Schema::dropIfExists('store_memberships');
        Schema::dropIfExists('platform_user_permissions');
        Schema::dropIfExists('stores');
    }
};
