<?php

use App\Models\SsoProvider;
use App\Services\Auth\OidcProvider;
use App\Services\Auth\SocialiteSsoDriverFactory;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Two\GithubProvider;
use Laravel\Socialite\Two\GoogleProvider;

function buildDriver(SsoProvider $provider): Provider
{
    // Outside an HTTP request: redirect() stores the OAuth state in the session.
    app('request')->setLaravelSession(app('session')->driver());

    return app(SocialiteSsoDriverFactory::class)->for($provider);
}

function authorizeUrl(Provider $driver): string
{
    return $driver->redirect()->getTargetUrl();
}

it('builds Google and GitHub with the provider callback URL', function () {
    $google = SsoProvider::factory()->create(['driver' => 'google', 'client_id' => 'g-id']);
    $github = SsoProvider::factory()->create(['driver' => 'github', 'client_id' => 'gh-id']);

    $googleDriver = buildDriver($google);
    expect($googleDriver)->toBeInstanceOf(GoogleProvider::class)
        ->and(authorizeUrl($googleDriver))
        ->toContain('accounts.google.com')
        ->toContain('client_id=g-id')
        ->toContain('redirect_uri='.urlencode(route('sso.callback', $google)));

    expect(buildDriver($github))->toBeInstanceOf(GithubProvider::class)
        ->and(authorizeUrl(buildDriver($github)))->toContain('client_id=gh-id');
});

it('builds a generic OIDC provider from the discovery document', function () {
    Http::fake(['https://auth.example.com/.well-known/openid-configuration' => Http::response([
        'issuer' => 'https://auth.example.com',
        'authorization_endpoint' => 'https://auth.example.com/authorize',
        'token_endpoint' => 'https://auth.example.com/token',
        'userinfo_endpoint' => 'https://auth.example.com/userinfo',
    ])]);
    $provider = SsoProvider::factory()->oidc('https://auth.example.com/')->create(['client_id' => 'o-id']);

    $driver = buildDriver($provider);

    expect($driver)->toBeInstanceOf(OidcProvider::class)
        ->and(authorizeUrl($driver))
        ->toStartWith('https://auth.example.com/authorize?')
        ->toContain('client_id=o-id')
        ->toContain('scope=openid+email+profile');
});

it('builds Microsoft as OIDC against the tenant issuer', function () {
    Http::fake(['https://login.microsoftonline.com/contoso.onmicrosoft.com/v2.0/.well-known/openid-configuration' => Http::response([
        'authorization_endpoint' => 'https://login.microsoftonline.com/contoso.onmicrosoft.com/oauth2/v2.0/authorize',
        'token_endpoint' => 'https://login.microsoftonline.com/contoso.onmicrosoft.com/oauth2/v2.0/token',
        'userinfo_endpoint' => 'https://graph.microsoft.com/oidc/userinfo',
    ])]);
    $provider = SsoProvider::factory()->create(['driver' => 'microsoft', 'settings' => ['tenant' => 'contoso.onmicrosoft.com']]);

    expect(authorizeUrl(buildDriver($provider)))
        ->toStartWith('https://login.microsoftonline.com/contoso.onmicrosoft.com/oauth2/v2.0/authorize?');
});
