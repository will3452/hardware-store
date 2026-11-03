<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class OrderRelease extends Model
{
    use BelongsToStore, HasUlids;

    protected $guarded = ['id', 'store_id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['released_at' => 'immutable_datetime', 'received_at' => 'immutable_datetime'];
    }
}
