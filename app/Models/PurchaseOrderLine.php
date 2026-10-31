<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int $factor_micro
 * @property int $quantity_micro
 * @property int $received_micro
 * @property int $total_cost_micro
 */
class PurchaseOrderLine extends Model
{
    use BelongsToStore, HasUlids;

    protected $guarded = ['id', 'store_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['factor_micro' => 'integer', 'quantity_micro' => 'integer', 'received_micro' => 'integer', 'total_cost_micro' => 'integer'];
    }
}
