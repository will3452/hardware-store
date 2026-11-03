<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecordPage
{
    public static function size(Request $request): int
    {
        $request->validate(['per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])]]);

        return $request->filled('per_page') ? $request->integer('per_page') : 10;
    }
}
