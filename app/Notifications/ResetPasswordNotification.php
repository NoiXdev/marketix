<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        // Pin to the recipient's preferred locale so queued sends are correct
        // even without an active request.
        $locale = method_exists($notifiable, 'preferredLocale')
            ? ($notifiable->preferredLocale() ?? config('app.fallback_locale'))
            : config('app.fallback_locale');

        $url = $this->resetUrl($notifiable);
        $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject(__('emails.reset_password.subject', [], $locale))
            ->greeting(__('emails.reset_password.greeting', [], $locale))
            ->line(__('emails.reset_password.line_1', [], $locale))
            ->action(__('emails.reset_password.action', [], $locale), $url)
            ->line(__('emails.reset_password.line_2', ['count' => $expire], $locale))
            ->line(__('emails.reset_password.line_3', [], $locale));
    }
}
