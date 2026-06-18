<?php

namespace Tests\Feature\Admin;

use App\Providers\MailSettingsServiceProvider;
use App\Settings\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MailSettingsOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_mailer_from_settings_overrides_config(): void
    {
        $settings = app(MailSettings::class);
        $settings->default_mailer = 'smtp';
        $settings->smtp_host = 'mail.example.test';
        $settings->save();

        // Re-run the provider boot now that settings exist.
        (new MailSettingsServiceProvider($this->app))->boot();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('mail.example.test', config('mail.mailers.smtp.host'));
    }

    public function test_reapplying_settings_reads_fresh_values_not_stale_cache(): void
    {
        $settings = app(MailSettings::class);
        $settings->default_mailer = 'smtp';
        $settings->save();

        $provider = new MailSettingsServiceProvider($this->app);
        $provider->boot();

        $this->assertSame('smtp', config('mail.default'));

        // Mutate ONLY the persisted DB row — the shared in-memory $settings
        // object still holds 'smtp'. This means the only way applyMailSettings()
        // can observe 'log' is by calling refresh() to reload from DB.
        // Mutating $settings->default_mailer directly (the old approach) updated
        // the shared singleton too, so the test passed even without refresh().
        DB::table('settings')
            ->where('group', 'mail')
            ->where('name', 'default_mailer')
            ->update(['payload' => json_encode('log')]);

        $this->app['events']->dispatch(new JobProcessing(
            'database',
            new SyncJob($this->app, '{}', 'sync', 'default'),
        ));

        // Can only pass if applyMailSettings() called refresh() — the shared
        // in-memory object still reports 'smtp'.
        $this->assertSame('log', config('mail.default'));
    }

    public function test_boot_is_silent_noop_when_settings_table_missing(): void
    {
        // Drop the settings table to simulate an un-migrated/fresh DB.
        Schema::drop('settings');

        // Set a sentinel value to assert boot() leaves config untouched
        // when the settings table is missing.
        config(['mail.default' => 'sentinel-value']);

        // Must not throw — falls back to .env/config defaults.
        (new MailSettingsServiceProvider($this->app))->boot();

        $this->assertSame('sentinel-value', config('mail.default'));
    }
}
