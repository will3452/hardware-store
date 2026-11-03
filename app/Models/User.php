<?php

namespace App\Models;

use App\Notifications\PasswordReset;
use App\Notifications\VerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property string $id
 * @property string $status
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUlids, Notifiable, TwoFactorAuthenticatable;

    protected $attributes = ['status' => 'active'];

    /** @return HasMany<StoreMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(StoreMembership::class);
    }

    /** @return HasMany<PlatformUserPermission, $this> */
    public function platformPermissions(): HasMany
    {
        return $this->hasMany(PlatformUserPermission::class);
    }

    public function hasPlatformPermission(string $permission): bool
    {
        return $this->status === 'active'
            && $this->platformPermissions()->where('permission', $permission)->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function sendPasswordResetNotification($token): void
    {
        if ($this->status === 'active') {
            $this->notify(new PasswordReset($token));
        }
    }

    public function sendEmailVerificationNotification(): void
    {
        if ($this->status === 'active' && ! $this->hasVerifiedEmail()) {
            RateLimiter::attempt('verification-email:'.$this->id.':'.hash('sha256', $this->email), 1, fn () => $this->notify(new VerifyEmail), 60);
        }
    }

    /** Get the user's initials. */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
