<?php

use App\Enums\SsoDriver;
use App\Models\AppSetting;
use App\Models\SsoProvider;
use App\Models\User;
use App\Support\Auth\LoginSettings;
use Illuminate\Support\Facades\DB;

it('keeps admin sign-in settings away from non-admins', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.sso.index'))->assertForbidden();
    $this->post(route('admin.sso.store'))->assertForbidden();
});

it('redirects guests to the login page', function () {
    $this->get(route('admin.sso.index'))->assertRedirect(route('login'));
});

it('lists providers with their callback URL', function () {
    $provider = SsoProvider::factory()->create(['name' => 'Work account']);

    $this->actingAs(User::factory()->admin()->create())->get(route('admin.sso.index'))
        ->assertOk()
        ->assertSee('Work account')
        ->assertSee(route('sso.callback', $provider));
});

it('adds a provider and stores its secret encrypted', function () {
    $this->actingAs(User::factory()->admin()->create())->post(route('admin.sso.store'), [
        'driver' => 'oidc',
        'name' => 'Authentik',
        'client_id' => 'client-1',
        'client_secret' => 'top-secret',
        'issuer' => 'https://auth.example.com/application/o/library/',
        'enabled' => '1',
        'allow_registration' => '0',
    ])->assertRedirect(route('admin.sso.index'));

    $provider = SsoProvider::sole();
    expect($provider->driver)->toBe(SsoDriver::Oidc)
        ->and($provider->client_secret)->toBe('top-secret')
        ->and($provider->settings['issuer'])->toBe('https://auth.example.com/application/o/library/')
        ->and($provider->enabled)->toBeTrue()
        ->and(DB::table('sso_providers')->value('client_secret'))->not->toContain('top-secret');
});

it('requires the issuer for OIDC and the tenant for Microsoft', function () {
    $admin = User::factory()->admin()->create();
    $base = ['name' => 'X', 'client_id' => 'id', 'client_secret' => 'secret'];

    $this->actingAs($admin)->post(route('admin.sso.store'), $base + ['driver' => 'oidc'])->assertSessionHasErrors('issuer');
    $this->post(route('admin.sso.store'), $base + ['driver' => 'microsoft'])->assertSessionHasErrors('tenant');
    $this->post(route('admin.sso.store'), $base + ['driver' => 'myspace'])->assertSessionHasErrors('driver');
    expect(SsoProvider::count())->toBe(0);
});

it('never renders the secret back and keeps it when the field is left empty', function () {
    $provider = SsoProvider::factory()->create(['client_secret' => 'top-secret']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.sso.edit', $provider))->assertOk()->assertDontSee('top-secret');

    $this->put(route('admin.sso.update', $provider), [
        'driver' => $provider->driver->value,
        'name' => 'Renamed',
        'client_id' => $provider->client_id,
        'client_secret' => '',
        'enabled' => '1',
    ])->assertRedirect(route('admin.sso.index'));

    expect($provider->refresh()->name)->toBe('Renamed')
        ->and($provider->client_secret)->toBe('top-secret');
});

it('deletes a provider and its linked identities', function () {
    $provider = SsoProvider::factory()->hasIdentities(2)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.sso.destroy', $provider))
        ->assertRedirect(route('admin.sso.index'));

    expect(SsoProvider::count())->toBe(0)
        ->and(DB::table('user_identities')->count())->toBe(0);
});

it('switches e-mail codes and e-mail sign-up', function () {
    SsoProvider::factory()->create();

    $this->actingAs(User::factory()->admin()->create())->put(route('admin.sso.settings'), [
        'email_codes_enabled' => '1',
        'email_registration_enabled' => '0',
    ])->assertRedirect(route('admin.sso.index'));

    expect(LoginSettings::emailCodesEnabled())->toBeTrue()
        ->and(LoginSettings::emailRegistrationEnabled())->toBeFalse();
});

it('refuses to switch e-mail codes off while no provider is enabled', function () {
    SsoProvider::factory()->disabled()->create();

    $this->actingAs(User::factory()->admin()->create())->put(route('admin.sso.settings'), [
        'email_codes_enabled' => '0',
        'email_registration_enabled' => '1',
    ])->assertSessionHasErrors('email_codes_enabled');

    expect(LoginSettings::emailCodesEnabled())->toBeTrue();
});

it('refuses to disable or delete the last enabled provider while e-mail codes are off', function () {
    $provider = SsoProvider::factory()->create();
    AppSetting::put(LoginSettings::EMAIL_CODES, false);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('admin.sso.update', $provider), [
        'driver' => $provider->driver->value,
        'name' => $provider->name,
        'client_id' => $provider->client_id,
        'enabled' => '0',
    ])->assertSessionHasErrors('enabled');

    $this->delete(route('admin.sso.destroy', $provider))->assertSessionHasErrors('provider');

    expect($provider->refresh()->enabled)->toBeTrue();
});

it('runs a test login that only reports what the provider returned', function () {
    $provider = SsoProvider::factory()->create();
    $admin = User::factory()->admin()->create();
    fakeSso(fakeSsoUser('abc', 'someone@idp.test', ['email_verified' => true]));

    $this->actingAs($admin)->get(route('admin.sso.test', $provider))->assertRedirect('https://idp.test/authorize');
    $this->get(route('sso.callback', $provider))
        ->assertRedirect(route('admin.sso.index'))
        ->assertSessionHas('status', fn (string $status) => str_contains($status, 'someone@idp.test'));

    expect($admin->identities()->count())->toBe(0);
    $this->assertAuthenticatedAs($admin);
});
