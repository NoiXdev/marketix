<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

class DemoSetupCommand extends Command
{
    protected $signature = 'marketix:demo:setup {--force}';

    protected $description = 'Rebuild the local development fixture using the demo seeder';

    public function handle(): int
    {
        if (! $this->option('force')) {
            if ($this->ask('Are you sure you want to do this?', 'yes') !== 'yes') {
                return self::SUCCESS;
            }
        }

        if ($this->call('migrate:fresh', ['--force' => true]) !== self::SUCCESS) {
            $this->error('Refusing to seed: migrate:fresh failed.');

            return self::FAILURE;
        }

        if ($this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]) !== self::SUCCESS) {
            $this->error('Local demo fixture rebuild failed: seeding did not complete.');

            return self::FAILURE;
        }

        $this->info('Local demo fixture rebuilt.');

        return self::SUCCESS;
    }
}
