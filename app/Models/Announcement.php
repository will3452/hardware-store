<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $title
 * @property string $body
 * @property bool $email_owners
 * @property CarbonImmutable|null $email_prepared_at
 * @property string $id
 * @property list<string>|null $store_ids
 * @property CarbonImmutable|null $publish_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $withdrawn_at
 */
class Announcement extends Model
{
    use HasUlids;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['email_owners' => 'boolean', 'email_prepared_at' => 'immutable_datetime', 'store_ids' => 'array', 'publish_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'withdrawn_at' => 'immutable_datetime'];
    }
}
