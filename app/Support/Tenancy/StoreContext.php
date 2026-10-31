<?php

namespace App\Support\Tenancy;

use App\Models\Store;
use Illuminate\Auth\Access\AuthorizationException;

final class StoreContext
{
    private ?Store $store = null;

    public function set(Store $store): void
    {
        $this->store = $store;
    }

    public function store(): Store
    {
        return $this->store ?? throw new AuthorizationException('A verified store context is required.');
    }

    public function id(): string
    {
        return (string) $this->store()->getKey();
    }

    public function clear(): void
    {
        $this->store = null;
    }
}
