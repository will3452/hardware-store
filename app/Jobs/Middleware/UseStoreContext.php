<?php

namespace App\Jobs\Middleware;

use App\Models\User;
use App\Support\Tenancy\StoreContext;
use Closure;

class UseStoreContext
{
    public function __construct(public string $storeId, public string $userId) {}

    public function handle(object $job, Closure $next): mixed
    {
        $context = app(StoreContext::class);
        $context->clear();

        try {
            $user = User::where('status', 'active')->findOrFail($this->userId);
            $membership = $user->memberships()->where('store_id', $this->storeId)
                ->where('status', 'active')->where('role', 'owner')
                ->whereHas('store', fn ($query) => $query->where('status', 'active'))
                ->with('store')->firstOrFail();

            $context->set($membership->store);

            return $next($job);
        } finally {
            $context->clear();
        }
    }
}
