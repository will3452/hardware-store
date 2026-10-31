<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property bool $fractional
 * @property bool $active
 * @property int $price_minor
 * @property int $reorder_micro
 * @property int $quantity_micro
 * @property int $value_micro
 * @property int $stock_version
 */
class Product extends Model
{
    use BelongsToStore, HasUlids;

    protected $guarded = ['id', 'store_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['fractional' => 'boolean', 'active' => 'boolean', 'price_minor' => 'integer', 'reorder_micro' => 'integer', 'quantity_micro' => 'integer', 'value_micro' => 'integer', 'stock_version' => 'integer'];
    }
}
