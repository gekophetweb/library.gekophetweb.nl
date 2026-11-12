<?php

use App\Models\SsoProvider;
use App\Models\User;
use App\Models\UserIdentity;
use App\Services\Auth\SsoDriverFactory;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Two\InvalidStateException;

it('shows a button per enabled provider on the login page, in order', function () {
    SsoProvider::factory()->create(['name' => 'Work account', 'sort' => 2]);
    SsoProvider::factory()->create(['name' => 'Google', 'sort' => 1]);
    SsoProvider::factory()->disabled()->create(['name' => 'Old IdP']);

    $this->get(route('login'))
        ->assertOk()
        ->assertSeeInOrder(['Continue with Google', 'Continue with Work account'])
        ->assertDontSee('Old IdP');
});

it('redirects to the identity provider', function () {
    $provider = SsoProvider::factory()->create();
    fakeSso(fakeSsoUser('abc', 'ann@example.com'));

    $this->get(route('sso.redirect', $provider))->assertRedirect('https://idp.test/authorize');
});

it('404s on a disabled provider', function () {
    $provider = SsoProvider::factory()->disabled()->create();
    fakeSso(fakeSsoUser('abc', 'ann@example.com'));

    $this->get(route('sso.redirect', $provider))->assertNotFound();
    $this->get(route('sso.callback', $provider))->assertNotFound();
});

it('logs in a user whose identity is already linked', function () {
    $provider = SsoProvider::factory()->create();
    $user = User::factory()->create();
    UserIdentity::factory()->for($user)->for($provider, 'provider')->create(['subject' => 'abc']);
    fakeSso(fakeSsoUser('abc', 'changed@example.com'));

    $this->get(route('sso.redirect', $provider));
    $this->get(route('sso.callback', $provider))->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

it('links and logs in an existing user with the same verified e-mail', function () {
    $provider = SsoProvider::factory()->create();
    $user = User::factory()->create(['email' => 'ann@example.com']);
    fakeSso(fakeSsoUser('abc', 'Ann@Example.com', ['email_verified' => true]));

    $this->get(route('sso.redirect', $provider));
    $this->get(route('sso.callback', $provider))->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
    expect($user->identities()->sole()->subject)->toBe('abc');
});

it('does not link by e-mail when the provider has not verified it', function () {
    $provider = SsoProvider::factory()->oidc()->create();
    User::factory()->create(['email' => 'ann@example.com']);
    fakeSso(fakeSsoUser('abc', 'ann@example.com', ['email_verified' => false]));

    $this->get(route('sso.redirect', $provider));
    $this->get(route('sso.callback', $provider))->assertRedirect(route('login'));

    $this->assertGuest();
    expect(UserIdentity::count())->toBe(0);
});

it('refuses an unknown e-mail when the provider does not allow sign-up', function () {
    $provider = SsoProvider::factory()->create(['allow_registration' => false]);
    fakeSso(fakeSsoUser('abc', 'new@example.com', ['email_verified' => true]));

    $this->get(route('sso.redirect', $provider));
    $this->get(route('sso.callback', $provider))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('sso');

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

it('creates an account for an unknown verified e-mail when the provider allows sign-up', function () {
    $provider = SsoProvider::factory()->create(['allow_registration' => true]);
    fakeSso(fakeSsoUser('abc', 'new@example.com', ['email_verified' => true], 'New Person'));

    $this->get(route('sso.redirect', $provider));
    $this->get(route('sso.callback', $provider))->assertRedirect(route('home'));

    $user = User::sole();
    expect($user->name)->toBe('New Person')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->identities()->sole()->subject)->toBe('abc');
    $this->assertAuthenticatedAs($user);
});

it('lets one user link two providers', function () {
    $google = SsoProvider::factory()->create();
    $work = SsoProvider::factory()->create();
    $user = User::factory()->create(['email' => 'ann@example.com']);

    foreach ([$google, $work] as $index => $provider) {
        fakeSso(fakeSsoUser("id-{$index}", 'ann@example.com', ['email_verified' => true]));
        $this->get(route('sso.redirect', $provider));
        $this->get(route('sso.callback', $provider));
        $this->post(route('logout'));
    }

    expect($user->identities()->count())->toBe(2);
});

it('changes the session id on login', function () {
    $provider = SsoProvider::factory()->create();
    $user = User::factory()->create();
    UserIdentity::factory()->for($user)->for($provider, 'provider')->create(['subject' => 'abc']);
    fakeSso(fakeSsoUser('abc', null));

    $this->get(route('sso.redirect', $provider));
    $before = session()->getId();
    $this->get(route('sso.callback', $provider));

    expect(session()->getId())->not->toBe($before);
});

it('sends the user back to the login page when the provider fails', function () {
    $provider = SsoProvider::factory()->create();
    app()->instance(SsoDriverFactory::class, new class implements SsoDriverFactory
    {
        public function for(SsoProvider $provider): Provider
        {
            throw new InvalidStateException;
        }
    });

    $this->get(route('sso.callback', $provider))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('sso');
    $this->assertGuest();
});
