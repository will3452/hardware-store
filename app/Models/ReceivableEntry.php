<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** @property int $amount_minor */
class ReceivableEntry extends Model
{
    use BelongsToStore, HasUlids;

    public $timestamps = false;

    protected $guarded = ['id', 'store_id'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'posted_at' => 'immutable_datetime'];
    }
}
