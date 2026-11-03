<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int $revision
 * @property int $quote_version
 * @property int $total_minor
 */
class SalesOrder extends Model
{
    use BelongsToStore, HasUlids;

    protected $guarded = ['id', 'store_id'];

    protected function casts(): array
    {
        return ['store_snapshot' => 'array', 'revision' => 'integer', 'quote_version' => 'integer', 'total_minor' => 'integer', 'issued_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime'];
    }
}
