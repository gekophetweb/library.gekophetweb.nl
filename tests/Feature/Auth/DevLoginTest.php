<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

function runningAs(string $environment): void
{
    app()->detectEnvironment(fn () => $environment);
}

beforeEach(function () {
    config(['auth.dev_user_email' => 'dev@example.com']);
    // Outside the "testing" environment the CSRF check is no longer skipped automatically.
    $this->withoutMiddleware(PreventRequestForgery::class);
});

it('signs in as the seeded dev user in local with the flag on', function () {
    runningAs('local');
    config(['auth.dev_sso_bypass' => true]);
    $dev = User::factory()->admin()->create(['email' => 'dev@example.com']);

    $this->get(route('login'))->assertSee('Sign in as dev user');
    $this->post(route('login.dev'))->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($dev);
});

it('is not available in local without the flag', function () {
    runningAs('local');
    config(['auth.dev_sso_bypass' => false]);
    User::factory()->create(['email' => 'dev@example.com']);

    $this->get(route('login'))->assertDontSee('Sign in as dev user');
    $this->post(route('login.dev'))->assertNotFound();
    $this->assertGuest();
});

it('is never available in production, even with the flag on', function () {
    runningAs('production');
    config(['auth.dev_sso_bypass' => true]);
    User::factory()->create(['email' => 'dev@example.com']);

    $this->get(route('login'))->assertDontSee('Sign in as dev user');
    $this->post(route('login.dev'))->assertNotFound();
    $this->assertGuest();
});

it('explains how to seed the dev user when it does not exist', function () {
    runningAs('local');
    config(['auth.dev_sso_bypass' => true]);

    $this->post(route('login.dev'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});
