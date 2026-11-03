<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Only existing full platform administrators receive the new data-access permission.
        // Limited administrators keep their reviewed grants.
        $existingFullAccess = ['platform.portal.access', 'platform.stores.manage', 'platform.modules.manage', 'platform.announcements.manage', 'platform.reports.read', 'platform.reports.export', 'platform.audit.read'];
        $ids = DB::table('platform_user_permissions')->whereIn('permission', $existingFullAccess)
            ->groupBy('user_id')->havingRaw('COUNT(DISTINCT permission) = ?', [count($existingFullAccess)])->pluck('user_id');
        foreach ($ids as $id) {
            DB::table('platform_user_permissions')->insertOrIgnore([
                'user_id' => $id, 'permission' => 'platform.stores.view', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Preserve grants that may have been reviewed after deployment.
    }
};
