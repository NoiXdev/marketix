<?php

namespace Tests\Feature\Demo;

use App\Console\Commands\DemoResetCommand;
use App\Console\Commands\DemoSetupCommand;
use Illuminate\Console\Command;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Tests\TestCase;

/**
 * Both commands discarded the return values of their internal `$this->call()`
 * steps, so a failed `migrate:fresh` would still seed a half-migrated
 * database and report SUCCESS — with no non-zero exit code and no signal
 * anywhere, since both run unattended (the reset runs nightly from the
 * scheduler).
 *
 * Simulating a genuine migrate:fresh failure (breaking the DB connection)
 * does not exercise the fixed code path cleanly: Illuminate\Console\
 * Concerns\CallsCommands::call() resolves and runs the sub-command directly
 * (`$this->resolveCommand($command)->run(...)`), with no exception-catching
 * layer around it — unlike the real `php artisan` front controller, which
 * wraps the whole run in Symfony's Application::run(). So a genuine DB
 * failure throws straight out of `$this->call()`, uncaught, before the
 * command's own `!== self::SUCCESS` check is ever reached. That failure
 * mode was already fine before this change; it isn't what the fix touches.
 *
 * What the fix changes is the OTHER failure mode: a sub-command that
 * completes and returns a non-SUCCESS integer without throwing. To exercise
 * exactly that, a fake `migrate:fresh` Symfony command is registered on a
 * throwaway Application and attached to our command via setApplication() —
 * this is what `resolveCommand()` consults to look up a string command name
 * (see Illuminate\Console\Command::resolveCommand()). It returns FAILURE
 * immediately, touching no database, and the command under test is invoked
 * directly via Symfony's Command::run() rather than Artisan::call(), so
 * db:seed is never reached — proving the early return actually happens.
 */
class DemoResetCommandTest extends TestCase
{
    private function attachFakeFailingMigrateFresh(Command $command): void
    {
        $app = new SymfonyApplication;
        $app->addCommand(new class extends \Symfony\Component\Console\Command\Command
        {
            protected function configure(): void
            {
                $this->setName('migrate:fresh')->addOption('force');
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return Command::FAILURE;
            }
        });
        // Registered so that, without the fix, the un-guarded second
        // $this->call() has something real to reach and succeed against —
        // making the pre-fix behaviour a clean "exits SUCCESS anyway"
        // rather than an unrelated "unknown command" error.
        $app->addCommand(new class extends \Symfony\Component\Console\Command\Command
        {
            protected function configure(): void
            {
                $this->setName('db:seed')->addOption('class')->addOption('force');
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return Command::SUCCESS;
            }
        });

        $command->setLaravel($this->app);
        $command->setApplication($app);
    }

    public function test_demo_reset_fails_when_migrate_fresh_fails(): void
    {
        config(['demo.enabled' => true]);

        $command = $this->app->make(DemoResetCommand::class);
        $this->attachFakeFailingMigrateFresh($command);

        $exitCode = $command->run(new ArrayInput([], $command->getDefinition()), new BufferedOutput);

        $this->assertSame(Command::FAILURE, $exitCode);
    }

    public function test_demo_setup_fails_when_migrate_fresh_fails(): void
    {
        $command = $this->app->make(DemoSetupCommand::class);
        $this->attachFakeFailingMigrateFresh($command);

        $exitCode = $command->run(new ArrayInput(['--force' => true], $command->getDefinition()), new BufferedOutput);

        $this->assertSame(Command::FAILURE, $exitCode);
    }
}
