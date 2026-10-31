<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property array<string, mixed> $metadata
 * @property CarbonImmutable $posted_at
 * @property int $gross_minor
 * @property int $discount_minor
 * @property int $total_minor
 * @property int $cost_micro
 * @property int $loss_micro
 * @property int $gain_micro
 */
class BusinessDocument extends Model
{
    use BelongsToStore, HasUlids;

    protected $guarded = ['id', 'store_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['metadata' => 'array', 'posted_at' => 'immutable_datetime', 'gross_minor' => 'integer', 'discount_minor' => 'integer', 'total_minor' => 'integer', 'cost_micro' => 'integer', 'loss_micro' => 'integer', 'gain_micro' => 'integer'];
    }
}
