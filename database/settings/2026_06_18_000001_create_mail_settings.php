<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('mail.default_mailer', config('mail.default', 'log'));
        $this->migrator->add('mail.from_address', config('mail.from.address', 'hello@example.com'));
        $this->migrator->add('mail.from_name', config('mail.from.name', 'Marketix'));
        $this->migrator->add('mail.postal_url', (string) config('postal.domain', ''));
        $this->migrator->addEncrypted('mail.postal_key', (string) config('postal.key', ''));
        $this->migrator->add('mail.smtp_host', (string) config('mail.mailers.smtp.host', ''));
        $this->migrator->add('mail.smtp_port', (int) config('mail.mailers.smtp.port', 587));
        $this->migrator->add('mail.smtp_username', (string) config('mail.mailers.smtp.username', ''));
        $this->migrator->addEncrypted('mail.smtp_password', (string) config('mail.mailers.smtp.password', ''));
        $this->migrator->add('mail.smtp_scheme', (string) config('mail.mailers.smtp.scheme', ''));
    }
};
