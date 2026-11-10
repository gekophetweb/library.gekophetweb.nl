<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(CatalogueSeeder::class);

        // The dev user the local SSO bypass signs in as (guidelines §2).
        $user = User::firstOrNew(['email' => config('auth.dev_user_email')]);
        $user->name ??= 'Dev User';
        $user->email_verified_at ??= now();
        $user->is_admin = true;
        $user->save();
    }
}
