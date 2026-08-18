<?php

namespace Tests\Feature\Demo;

use App\Providers\DemoServiceProvider;
use App\Providers\MailSettingsServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DemoMailSeveranceTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_default_is_forced_to_log_in_demo_mode(): void
    {
        config(['demo.enabled' => true]);

        // Re-run the provider's apply path, exactly as JobProcessing does.
        (new MailSettingsServiceProvider($this->app))->boot();

        $this->assertSame('log', config('mail.default'));
    }

    public function test_message_sending_is_cancelled_in_demo_mode(): void
    {
        config(['demo.enabled' => true]);
        (new DemoServiceProvider($this->app))->boot();

        // MessageSent fires only once a message has actually been handed to
        // a transport. No Mail::fake() here — that would swap the mailer
        // out entirely and bypass the MessageSending listener under test.
        $sentCount = 0;
        Event::listen(MessageSent::class, function () use (&$sentCount) {
            $sentCount++;
        });

        Mail::raw('hello', fn ($m) => $m->to('someone@example.com')->subject('Test'));

        $this->assertSame(0, $sentCount);
    }

    public function test_mail_settings_are_untouched_when_demo_mode_is_off(): void
    {
        config(['demo.enabled' => false, 'mail.default' => 'smtp']);

        (new MailSettingsServiceProvider($this->app))->boot();

        $this->assertNotSame('log', config('mail.default'));
    }
}
