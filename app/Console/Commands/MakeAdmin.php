<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

#[Signature('users:make-admin {email : E-mail address of the user to promote (created if unknown)}')]
#[Description('Make a user an administrator, creating the user if needed')]
class MakeAdmin extends Command
{
    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->error("\"{$email}\" is not a valid e-mail address.");

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->name = Str::before($email, '@');
            $user->email_verified_at = now();
        }

        $user->is_admin = true;
        $user->save();

        $this->info("{$email} is now an administrator.");

        return self::SUCCESS;
    }
}
