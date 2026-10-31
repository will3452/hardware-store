<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 */
class PurchaseOrder extends Model
{
    use BelongsToStore, HasUlids;

    protected $guarded = ['id', 'store_id'];
}
