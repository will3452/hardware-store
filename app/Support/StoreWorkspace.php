<?php

namespace App\Support;

use App\Support\Tenancy\StoreContext;

final class StoreWorkspace
{
    /** Keep navigation in the administrator's explicit read-only route family. */
    public static function route(string $name, mixed $parameters = []): string
    {
        if (app(StoreContext::class)->isReadOnly()) {
            $name = 'admin.stores.view.'.substr($name, strlen('store.'));
        }

        return route($name, $parameters);
    }
}
