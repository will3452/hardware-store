<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int $factor_micro
 * @property int $quantity_micro
 * @property int $released_micro
 * @property int $cancelled_micro
 * @property int $price_minor
 * @property int $gross_minor
 * @property int $discount_minor
 * @property int $total_minor
 * @property int $released_total_minor
 * @property int $released_discount_minor
 */
class SalesOrderLine extends Model
{
    use BelongsToStore, HasUlids;

    protected $guarded = ['id', 'store_id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['factor_micro' => 'integer', 'quantity_micro' => 'integer', 'released_micro' => 'integer', 'cancelled_micro' => 'integer', 'price_minor' => 'integer', 'gross_minor' => 'integer', 'discount_minor' => 'integer', 'total_minor' => 'integer', 'released_total_minor' => 'integer', 'released_discount_minor' => 'integer'];
    }
}
