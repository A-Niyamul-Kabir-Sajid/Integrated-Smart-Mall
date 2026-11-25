<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

#[Signature('map:admin {email} {--name=Map admin} {--password= : Leave out to be asked}')]
#[Description('Create a map service administrator, or reset the password of an existing one')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $email = (string) $this->argument('email');
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Give a valid email address.');

            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?? $this->secret('Password (at least 8 characters)'));
        if (strlen($password) < 8) {
            $this->error('The password needs at least 8 characters.');

            return self::FAILURE;
        }

        $user = User::query()->updateOrCreate(['email' => $email], [
            'name' => (string) $this->option('name'),
            'password' => Hash::make($password),
        ]);
        $this->info(($user->wasRecentlyCreated ? 'Created' : 'Updated')." administrator {$email}. Sign in at ".url('/login'));

        return self::SUCCESS;
    }
}
