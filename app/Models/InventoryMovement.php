<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int $quantity_micro
 * @property int $value_micro
 * @property int $stock_version
 */
class InventoryMovement extends Model
{
    use BelongsToStore, HasUlids;

    protected $guarded = ['id', 'store_id'];

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity_micro' => 'integer', 'value_micro' => 'integer', 'stock_version' => 'integer'];
    }
}
