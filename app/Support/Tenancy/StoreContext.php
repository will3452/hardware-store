<?php

namespace App\Support\Tenancy;

use App\Models\Store;
use Illuminate\Auth\Access\AuthorizationException;

final class StoreContext
{
    private ?Store $store = null;

    private bool $readOnly = false;

    public function set(Store $store, bool $readOnly = false): void
    {
        $this->store = $store;
        $this->readOnly = $readOnly;
    }

    public function store(): Store
    {
        return $this->store ?? throw new AuthorizationException('A verified store context is required.');
    }

    public function id(): string
    {
        return (string) $this->store()->getKey();
    }

    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    public function clear(): void
    {
        $this->store = null;
        $this->readOnly = false;
    }
}
