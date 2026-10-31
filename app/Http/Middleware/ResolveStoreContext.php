<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\StoreContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveStoreContext
{
    public function __construct(private StoreContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->clear();

        try {
            $membership = $request->user()->memberships()
                ->where('store_id', $request->route('store'))
                ->where('status', 'active')
                ->where('role', 'owner')
                ->whereHas('store', fn ($query) => $query->where('status', 'active'))
                ->with('store')->firstOrFail();

            $this->context->set($membership->store);

            return $next($request);
        } finally {
            $this->context->clear();
        }
    }
}
