<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Store;
use App\Models\StoreMembership;
use App\Models\StoreModule;
use App\Models\User;
use App\Support\Tenancy\StoreContext;
use Illuminate\Validation\ValidationException;

final class StoreAccess
{
    public const MODULES = ['catalog', 'inventory', 'cash', 'sales', 'purchasing', 'expenses', 'reports', 'customers', 'credit', 'orders'];

    public const DEPENDENCIES = [
        'inventory' => ['catalog'], 'sales' => ['catalog', 'inventory', 'cash'],
        'purchasing' => ['catalog', 'inventory'], 'expenses' => ['cash'],
        'credit' => ['customers', 'sales', 'cash'], 'orders' => ['customers', 'catalog'],
    ];

    public const PERMISSIONS = ['platform.portal.access', 'platform.stores.manage', 'platform.stores.view', 'platform.modules.manage', 'platform.announcements.manage', 'platform.reports.read', 'platform.reports.export', 'platform.audit.read'];

    public function lock(User $actor): Store
    {
        abort_if(app(StoreContext::class)->isReadOnly(), 403, 'Administrator store viewing is read-only.');
        $store = Store::whereKey(app(StoreContext::class)->id())->lockForUpdate()->firstOrFail();
        abort_unless($store->status === 'active' && $actor->fresh()?->status === 'active'
            && StoreMembership::where('store_id', $store->id)->where('user_id', $actor->id)
                ->where('role', 'owner')->where('status', 'active')->exists(), 403, 'Store access changed. Refresh before continuing.');
        app(StoreContext::class)->set($store);

        return $store;
    }

    public function module(string $module, bool $resolution = false): void
    {
        $mode = StoreModule::where('module_key', $module)->value('mode');
        abort_unless($mode === 'enabled' || ($resolution && $mode === 'restricted'), 403, 'This module is not enabled for this operation.');
    }

    public function history(string $module): void
    {
        if ($module === 'reports' && ! app(StoreContext::class)->isReadOnly()) {
            $this->module('reports');
        }
    }

    public static function requirePermission(User $actor, string $permission): void
    {
        abort_unless($actor->hasPlatformPermission($permission), 403);
    }

    /** @param array<string, mixed> $details */
    public static function audit(User $actor, ?string $storeId, string $action, ?string $subjectId = null, array $details = []): void
    {
        AuditEvent::create(['store_id' => $storeId, 'user_id' => $actor->id, 'action' => $action, 'subject_id' => $subjectId, 'details' => $details]);
    }

    public static function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['operation' => $message]);
        }
    }
}
