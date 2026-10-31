<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int $factor_micro
 * @property int $quantity_micro
 * @property int $price_minor
 * @property int $gross_minor
 * @property int $discount_minor
 * @property int $total_minor
 * @property int $cost_micro
 */
class DocumentLine extends Model
{
    use BelongsToStore, HasUlids;

    protected $guarded = ['id', 'store_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['factor_micro' => 'integer', 'quantity_micro' => 'integer', 'price_minor' => 'integer', 'gross_minor' => 'integer', 'discount_minor' => 'integer', 'total_minor' => 'integer', 'cost_micro' => 'integer'];
    }
}
