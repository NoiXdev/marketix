<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ResetPasswordEmailLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_email_is_sent_in_user_locale(): void
    {
        Notification::fake();

        $user = User::factory()->create(['locale' => 'de']);
        $user->sendPasswordResetNotification('tok-123');

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification, $channels, $notifiable) {
            $mail = $notification->toMail($notifiable);
            // Subject reflects the German catalog.
            return $mail->subject === 'Passwort zurücksetzen';
        });
    }
}
