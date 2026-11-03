<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\AccountEmail;

class VerifyEmail extends \Illuminate\Auth\Notifications\VerifyEmail
{
    public function link(User $user): string
    {
        return $this->verificationUrl($user);
    }

    /** @return list<class-string> */
    public function via($notifiable): array
    {
        return [AccountEmail::class];
    }
}
