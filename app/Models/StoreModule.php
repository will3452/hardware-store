<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class StoreModule extends Model
{
    use BelongsToStore, HasUlids;

    protected $fillable = ['module_key', 'mode'];
}
