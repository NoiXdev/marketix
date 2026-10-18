<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\ActivityRecorder;
use App\Support\LoginLink;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class LoginLinkCommand extends Command
{
    protected $signature = 'marketix:login-link
        {user : The user ID or email address to sign in as}';

    protected $description = 'Generate a short-lived, single-use link that signs you straight into a user account';

    public function handle(): int
    {
        $identifier = (string) $this->argument('user');
        $user = $this->resolve($identifier);

        if ($user === null) {
            $this->error("No user found for \"{$identifier}\".");

            return self::FAILURE;
        }

        $token = LoginLink::issue($user);

        ActivityRecorder::security('login_link.issued', $user);

        $this->line(route('app.auth.login-link', ['token' => $token]));
        $this->newLine();
        $this->line(sprintf(
            'Valid for %d minutes, single use. Issued for %s (%s).',
            LoginLink::TTL_MINUTES,
            $user->email,
            $user->getKey(),
        ));

        return self::SUCCESS;
    }

    private function resolve(string $identifier): ?User
    {
        if (Str::contains($identifier, '@')) {
            return User::where('email', $identifier)->first();
        }

        return User::find($identifier);
    }
}
