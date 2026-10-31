<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property CarbonImmutable $opened_at
 * @property CarbonImmutable|null $closed_at
 * @property int $opening_minor
 * @property int $expected_minor
 * @property int|null $counted_minor
 * @property int|null $variance_minor
 */
class CashSession extends Model
{
    use BelongsToStore, HasUlids;

    protected $guarded = ['id', 'store_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['opened_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime', 'opening_minor' => 'integer', 'expected_minor' => 'integer', 'counted_minor' => 'integer', 'variance_minor' => 'integer'];
    }
}
