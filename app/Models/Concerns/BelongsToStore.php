<?php

namespace App\Models\Concerns;

use App\Support\Tenancy\StoreContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToStore
{
    protected static function bootBelongsToStore(): void
    {
        static::addGlobalScope('store', function (Builder $query): void {
            $query->where($query->getModel()->qualifyColumn('store_id'), app(StoreContext::class)->id());
        });

        static::saving(function (Model $model): void {
            if (app(StoreContext::class)->isReadOnly()) {
                throw new AuthorizationException('Administrator store viewing is read-only.');
            }
            $storeId = app(StoreContext::class)->id();

            if ($model->getAttribute('store_id') !== null && $model->getAttribute('store_id') !== $storeId) {
                throw new AuthorizationException('The resource does not belong to the selected store.');
            }

            if ($model->exists && $model->getOriginal('store_id') !== $storeId) {
                throw new AuthorizationException('Store ownership cannot be changed.');
            }

            $model->setAttribute('store_id', $storeId);
        });

        static::deleting(function (Model $model): void {
            if (app(StoreContext::class)->isReadOnly()) {
                throw new AuthorizationException('Administrator store viewing is read-only.');
            }
            if ($model->getOriginal('store_id') !== app(StoreContext::class)->id()) {
                throw new AuthorizationException('The resource does not belong to the selected store.');
            }
        });
    }
}
