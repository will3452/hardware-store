<?php

namespace App\Notifications;

use App\Notifications\Channels\AccountEmail;
use Illuminate\Auth\Notifications\ResetPassword;

class PasswordReset extends ResetPassword
{
    /** @return list<class-string> */
    public function via($notifiable): array
    {
        return [AccountEmail::class];
    }
}
