<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdminCommand extends Command
{
    protected $signature = 'marketix:create-admin
        {--name= : The admin display name}
        {--email= : The admin email address}
        {--password= : The admin password (prompted securely if omitted)}
        {--force : Skip the confirmation when promoting an existing user}';

    protected $description = 'Create a super admin user, or promote an existing user to super admin';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email') ?: $this->ask('Email');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email'],
            ],
        );

        if ($validator->fails()) {
            $this->reportErrors($validator);

            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();

        if ($existing) {
            return $this->promote($existing);
        }

        return $this->create($name, $email);
    }

    private function promote(User $user): int
    {
        if ($user->super_admin) {
            $this->info("{$user->email} is already a super admin. Nothing to do.");

            return self::SUCCESS;
        }

        if (! $this->option('force')
            && ! $this->confirm("User {$user->email} already exists. Promote to super admin?", true)) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        // Granting the super admin role only — never reset the existing password.
        $user->super_admin = true;
        $user->save();

        $this->info("Promoted {$user->email} to super admin.");

        return self::SUCCESS;
    }

    private function create(string $name, string $email): int
    {
        $password = $this->resolvePassword();

        if ($password === null) {
            return self::FAILURE;
        }

        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', 'min:8']],
        );

        if ($validator->fails()) {
            $this->reportErrors($validator);

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        // super_admin is not mass-assignable, so set it explicitly.
        $user->super_admin = true;
        $user->force_password_change = false;
        $user->save();

        $this->info("Created super admin {$user->name} <{$user->email}>.");

        return self::SUCCESS;
    }

    private function resolvePassword(): ?string
    {
        if ($password = $this->option('password')) {
            return $password;
        }

        $password = $this->secret('Password');

        if ($password !== $this->secret('Confirm password')) {
            $this->error('Passwords do not match.');

            return null;
        }

        return $password;
    }

    private function reportErrors(\Illuminate\Validation\Validator $validator): void
    {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }
    }
}
