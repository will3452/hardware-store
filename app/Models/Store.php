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
        'currency', 'timezone', 'status', 'checkout_methods',
    ];

    public const PAYMENT_METHODS = ['cash' => 'Cash', 'ewallet' => 'E-wallet', 'bank' => 'Bank transfer', 'card' => 'Card'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['checkout_methods' => 'array'];
    }

    /** @return array<string, string> */
    public function acceptedPayments(): array
    {
        return array_intersect_key(self::PAYMENT_METHODS, array_flip($this->checkout_methods ?? array_keys(self::PAYMENT_METHODS)));
    }
}
