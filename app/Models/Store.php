<?php

namespace App\Models;

use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'code', 'display_name', 'address_line_1', 'address_line_2', 'city',
        'province', 'postal_code', 'country_code', 'contact_email', 'contact_phone',
        'currency', 'timezone', 'status',
    ];
}
