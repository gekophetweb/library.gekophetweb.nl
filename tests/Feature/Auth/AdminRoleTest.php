<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Gate;

it('makes new users non-admins by default', function () {
    $user = User::factory()->create();

    expect($user->refresh()->is_admin)->toBeFalse();
});

it('lets only admins pass the admin gate', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    expect(Gate::forUser($admin)->allows('admin'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('admin'))->toBeFalse();
});

it('promotes an existing user with users:make-admin', function () {
    $user = User::factory()->create(['email' => 'ann@example.com']);

    $this->artisan('users:make-admin', ['email' => 'ann@example.com'])->assertSuccessful();

    expect($user->refresh()->is_admin)->toBeTrue()
        ->and(User::count())->toBe(1);
});

it('creates a verified admin with users:make-admin when the e-mail is unknown', function () {
    $this->artisan('users:make-admin', ['email' => 'New@Example.com'])->assertSuccessful();

    $user = User::sole();

    expect($user->email)->toBe('new@example.com')
        ->and($user->is_admin)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();
});

it('rejects an invalid e-mail in users:make-admin', function () {
    $this->artisan('users:make-admin', ['email' => 'not-an-email'])->assertFailed();

    expect(User::count())->toBe(0);
});

it('seeds the configured dev user as an admin', function () {
    config(['auth.dev_user_email' => 'dev@example.com']);

    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'dev@example.com')->sole()->is_admin)->toBeTrue();
});
