<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property array<string, mixed> $details
 */
class AuditEvent extends Model
{
    use HasUlids;

    protected $guarded = ['id'];

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['details' => 'array'];
    }
}
