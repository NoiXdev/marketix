<?php

namespace Tests\Feature\Demo;

use App\Console\Commands\DemoResetCommand;
use App\Console\Commands\DemoSetupCommand;
use Illuminate\Console\Command;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Tests\TestCase;

/**
 * Both DemoResetCommand and DemoSetupCommand discarded the return values of
 * their internal `$this->call()` steps, so a failed `migrate:fresh` OR a
 * failed `db:seed` would still report SUCCESS — with no non-zero exit code
 * and no signal anywhere, since the reset runs unattended nightly from the
 * scheduler.
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
 * completes and returns a non-SUCCESS integer without throwing — for
 * EITHER of the two steps. To exercise exactly that, fake `migrate:fresh`
 * and `db:seed` Symfony commands are registered on a throwaway
 * Application and attached to the command under test via
 * setApplication() — this is what `resolveCommand()` consults to look up
 * a string command name (see Illuminate\Console\Command::resolveCommand()).
 * Neither fake touches a database. The command under test is invoked
 * directly via Symfony's Command::run() rather than Artisan::call(), so
 * the swap stays scoped to the command's own internal `$this->call()`.
 *
 * Deliberately does NOT use DatabaseMigrations/RefreshDatabase: no real
 * migration or seed ever runs here. The real end-to-end wipe-and-reseed
 * and the "refuses outside demo mode" guard are covered separately in
 * DemoResetCommandTest.
 */
class DemoResetExitCodeTest extends TestCase
{
    private function fakeCommand(string $name, array $options, int $exitCode): SymfonyCommand
    {
        return new class($name, $options, $exitCode) extends SymfonyCommand
        {
            public function __construct(private string $cmdName, private array $opts, private int $exit)
            {
                parent::__construct();
            }

            protected function configure(): void
            {
                $this->setName($this->cmdName);

                foreach ($this->opts as $option) {
                    $this->addOption($option);
                }
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return $this->exit;
            }
        };
    }

    private function attachFakeSubCommands(Command $command, int $migrateFreshExit, int $dbSeedExit): void
    {
        $app = new SymfonyApplication;
        $app->addCommand($this->fakeCommand('migrate:fresh', ['force'], $migrateFreshExit));
        $app->addCommand($this->fakeCommand('db:seed', ['class', 'force'], $dbSeedExit));

        $command->setLaravel($this->app);
        $command->setApplication($app);
    }

    private function runCommand(Command $command, array $input = []): int
    {
        return $command->run(new ArrayInput($input, $command->getDefinition()), new BufferedOutput);
    }

    public function test_demo_reset_fails_when_migrate_fresh_fails(): void
    {
        config(['demo.enabled' => true]);

        $command = $this->app->make(DemoResetCommand::class);
        $this->attachFakeSubCommands($command, migrateFreshExit: Command::FAILURE, dbSeedExit: Command::SUCCESS);

        $this->assertSame(Command::FAILURE, $this->runCommand($command));
    }

    public function test_demo_reset_fails_when_db_seed_fails(): void
    {
        config(['demo.enabled' => true]);

        $command = $this->app->make(DemoResetCommand::class);
        $this->attachFakeSubCommands($command, migrateFreshExit: Command::SUCCESS, dbSeedExit: Command::FAILURE);

        $this->assertSame(Command::FAILURE, $this->runCommand($command));
    }

    public function test_demo_setup_fails_when_migrate_fresh_fails(): void
    {
        $command = $this->app->make(DemoSetupCommand::class);
        $this->attachFakeSubCommands($command, migrateFreshExit: Command::FAILURE, dbSeedExit: Command::SUCCESS);

        $this->assertSame(Command::FAILURE, $this->runCommand($command, ['--force' => true]));
    }

    public function test_demo_setup_fails_when_db_seed_fails(): void
    {
        $command = $this->app->make(DemoSetupCommand::class);
        $this->attachFakeSubCommands($command, migrateFreshExit: Command::SUCCESS, dbSeedExit: Command::FAILURE);

        $this->assertSame(Command::FAILURE, $this->runCommand($command, ['--force' => true]));
    }
}
