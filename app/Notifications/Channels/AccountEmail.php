<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\PasswordReset;
use App\Notifications\VerifyEmail;
use App\Services\EmailOutbox;

class AccountEmail
{
    public function __construct(private EmailOutbox $outbox) {}

    public function send(User $user, PasswordReset|VerifyEmail $notification): void
    {
        if ($notification instanceof PasswordReset) {
            $this->outbox->passwordReset($user, $notification->token);
        } else {
            $this->outbox->enqueue('verification', $user->email, [
                'title' => 'Verify your email address',
                'body' => 'Confirm this email address to continue using your Pyesio account.',
                'action_url' => $notification->link($user),
                'action_text' => 'Verify email',
                'note' => 'This link expires in 60 minutes. If you did not request it, you can ignore this email.',
            ], user: $user, expires: now()->addMinutes(60));
        }
    }
}
