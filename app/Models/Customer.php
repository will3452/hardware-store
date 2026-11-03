<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property bool $active
 * @property bool $credit_enabled
 * @property int $credit_limit_minor
 * @property int $term_days
 * @property int $revision
 */
class Customer extends Model
{
    use BelongsToStore, HasUlids;

    protected $guarded = ['id', 'store_id'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'credit_enabled' => 'boolean', 'credit_limit_minor' => 'integer', 'term_days' => 'integer', 'revision' => 'integer'];
    }
}
