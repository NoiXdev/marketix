<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

/**
 * Wipes the demo instance and rebuilds it. Runs unattended from the scheduler,
 * so it takes no confirmation — the refusal below is what keeps it from ever
 * touching a non-demo database.
 */
class DemoResetCommand extends Command
{
    protected $signature = 'marketix:demo:reset';

    protected $description = 'Wipe the demo instance and re-seed it with fresh demo data';

    public function handle(): int
    {
        if (! config('demo.enabled')) {
            $this->error('Refusing to run: this instance is not in demo mode (DEMO_MODE is not true).');

            return self::FAILURE;
        }

        $this->call('migrate:fresh', ['--force' => true]);
        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        $this->info('Demo instance reset.');

        return self::SUCCESS;
    }
}
