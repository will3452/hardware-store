<?php

use App\Services\StoreAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('platform_user_permissions')->where('permission', 'platform.portal.access')->pluck('user_id') as $userId) {
            foreach (StoreAccess::PERMISSIONS as $permission) {
                DB::table('platform_user_permissions')->insertOrIgnore(['user_id' => $userId, 'permission' => $permission, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // Explicit permissions may have been reviewed after deployment; retain them.
    }
};
