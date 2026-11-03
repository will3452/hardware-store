<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class SalesOrderAction extends Model
{
    use BelongsToStore, HasUlids;

    protected $guarded = ['id', 'store_id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }
}
