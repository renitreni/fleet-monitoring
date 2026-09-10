<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class AccountPasswordReset extends ResetPassword
{
    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your password change')
            ->line('Use the link below to verify your email and choose a new password for your account. Sign in to the same account if prompted.')
            ->action('Change password', route('account.show', ['token' => $this->token]))
            ->line('This link expires in '.config('auth.passwords.'.config('fortify.passwords').'.expire').' minutes and can only be used once.')
            ->line('If you did not request this change, you can ignore this email. Your password has not changed.');
    }
}
