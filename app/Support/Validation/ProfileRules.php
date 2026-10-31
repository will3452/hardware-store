<?php

namespace App\Support\Validation;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

final class ProfileRules
{
    /** @return array<string, list<string|Unique>> */
    public static function forUser(User $user): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique(User::class)->ignore($user->getKey()),
            ],
        ];
    }
}
