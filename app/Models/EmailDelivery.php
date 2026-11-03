<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string|null $store_id
 * @property string|null $user_id
 * @property string|null $source_id
 * @property string $kind
 * @property string $recipient
 * @property string $dedupe_key
 * @property array<string, string>|null $payload
 * @property string $status
 * @property int $attempts
 * @property CarbonImmutable $available_at
 * @property CarbonImmutable|null $lease_until
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $sent_at
 * @property string|null $last_error
 */
class EmailDelivery extends Model
{
    use HasUlids;

    protected $guarded = ['id'];

    protected $hidden = ['payload'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'attempts' => 'integer', 'available_at' => 'immutable_datetime', 'lease_until' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'sent_at' => 'immutable_datetime'];
    }
}
