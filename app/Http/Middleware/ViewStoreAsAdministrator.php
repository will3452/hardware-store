<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Services\StoreAccess;
use App\Support\Tenancy\StoreContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ViewStoreAsAdministrator
{
    public function __construct(private StoreContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->clear();

        try {
            StoreAccess::requirePermission($request->user(), 'platform.stores.view');
            abort_unless(in_array($request->method(), ['GET', 'HEAD'], true), 403, 'Administrator store viewing is read-only.');
            if ($request->filled('export')) {
                StoreAccess::requirePermission($request->user(), 'platform.reports.export');
            }
            $storeId = $request->route('store');
            abort_unless(is_string($storeId), 404);
            $store = Store::whereKey($storeId)->firstOrFail();
            $this->context->set($store, readOnly: true);
            $response = $next($request);
            if ($response->isSuccessful()) {
                $resource = $request->route('document') ?? $request->route('order') ?? $request->route('cashSession') ?? $request->route('attachment');
                StoreAccess::audit($request->user(), $store->id, 'platform.store_viewed', $resource ?? $store->id, [
                    'route' => $request->route()?->getName(),
                    'page' => $request->route('page', 'overview'),
                    'export' => $request->filled('export'),
                    'read_only' => true,
                ]);
            }

            return $response;
        } finally {
            $this->context->clear();
        }
    }
}
