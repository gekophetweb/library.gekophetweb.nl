<?php

use App\Models\AppSetting;
use App\Models\SsoProvider;
use App\Models\User;
use App\Models\UserIdentity;
use App\Support\Auth\LoginSettings;

it('lists the linked sign-in methods in settings', function () {
    $provider = SsoProvider::factory()->create(['name' => 'Work account']);
    $user = User::factory()->create();
    UserIdentity::factory()->for($user)->for($provider, 'provider')->create(['email' => 'ann@work.test']);
    SsoProvider::factory()->create(['name' => 'Google']);

    $this->actingAs($user)->get(route('settings.edit'))
        ->assertOk()
        ->assertSee('Linked sign-in methods')
        ->assertSee('ann@work.test')
        ->assertSee('Link Google');
});

it('links a provider to the signed-in user', function () {
    $provider = SsoProvider::factory()->create();
    $user = User::factory()->create(['email' => 'ann@example.com']);
    fakeSso(fakeSsoUser('abc', 'ann@other.test'));

    $this->actingAs($user)->get(route('sso.redirect', $provider));
    $this->get(route('sso.callback', $provider))->assertRedirect(route('settings.edit'));

    expect($user->identities()->sole()->subject)->toBe('abc');
});

it('refuses to link an identity that belongs to another user', function () {
    $provider = SsoProvider::factory()->create();
    $other = User::factory()->create();
    UserIdentity::factory()->for($other)->for($provider, 'provider')->create(['subject' => 'abc']);
    $user = User::factory()->create();
    fakeSso(fakeSsoUser('abc', null));

    $this->actingAs($user)->get(route('sso.redirect', $provider));
    $this->get(route('sso.callback', $provider))
        ->assertRedirect(route('settings.edit'))
        ->assertSessionHasErrors('sso');

    expect($user->identities()->count())->toBe(0)
        ->and($other->identities()->count())->toBe(1);
});

it('unlinks an identity', function () {
    $user = User::factory()->create();
    $identity = UserIdentity::factory()->for($user)->create();

    $this->actingAs($user)->delete(route('settings.identities.destroy', $identity))
        ->assertRedirect(route('settings.edit'));

    expect(UserIdentity::count())->toBe(0);
});

it('keeps the last identity when e-mail codes are off', function () {
    AppSetting::put(LoginSettings::EMAIL_CODES, false);
    $user = User::factory()->create();
    $identity = UserIdentity::factory()->for($user)->create();

    $this->actingAs($user)->delete(route('settings.identities.destroy', $identity))
        ->assertSessionHasErrors('identity');

    expect(UserIdentity::count())->toBe(1);
});

it('does not let a user unlink someone else\'s identity', function () {
    $identity = UserIdentity::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('settings.identities.destroy', $identity))
        ->assertNotFound();

    expect(UserIdentity::count())->toBe(1);
});

it('lets the user change their name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('settings.update'), ['name' => 'Ann B'])
        ->assertRedirect(route('settings.edit'));

    expect($user->refresh()->name)->toBe('Ann B');
});
