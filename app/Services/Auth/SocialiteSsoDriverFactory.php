<?php

namespace App\Services\Auth;

use App\Enums\SsoDriver;
use App\Models\SsoProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GithubProvider;
use Laravel\Socialite\Two\GoogleProvider;
use RuntimeException;

/**
 * Builds the Socialite driver for a provider row at runtime, so providers are data and
 * not config/services.php entries. Microsoft is plain OIDC against its tenant issuer.
 */
class SocialiteSsoDriverFactory implements SsoDriverFactory
{
    public function __construct(private readonly Request $request) {}

    public function for(SsoProvider $provider): Provider
    {
        $config = [
            'client_id' => $provider->client_id,
            'client_secret' => $provider->client_secret,
            'redirect' => route('sso.callback', $provider),
        ];

        return match ($provider->driver) {
            SsoDriver::Google => Socialite::buildProvider(GoogleProvider::class, $config),
            SsoDriver::Github => Socialite::buildProvider(GithubProvider::class, $config),
            SsoDriver::Microsoft => $this->oidc($provider, $config, 'https://login.microsoftonline.com/'.rawurlencode((string) $provider->setting('tenant')).'/v2.0'),
            SsoDriver::Oidc => $this->oidc($provider, $config, (string) $provider->setting('issuer')),
        };
    }

    /** @param  array{client_id: string, client_secret: string, redirect: string}  $config */
    private function oidc(SsoProvider $provider, array $config, string $issuer): OidcProvider
    {
        $driver = new OidcProvider($this->request, $config['client_id'], $config['client_secret'], $config['redirect']);
        $driver->useDiscovery($this->discover($issuer));

        if ($scopes = $provider->setting('scopes')) {
            $driver->setScopes(preg_split('/[\s,]+/', $scopes, flags: PREG_SPLIT_NO_EMPTY));
        }

        return $driver;
    }

    /** @return array<string, mixed> */
    private function discover(string $issuer): array
    {
        $url = rtrim($issuer, '/').'/.well-known/openid-configuration';

        return Cache::remember('sso:discovery:'.sha1($url), now()->addHour(), function () use ($url) {
            $document = Http::timeout(10)->acceptJson()->get($url)->throw()->json();

            foreach (['authorization_endpoint', 'token_endpoint', 'userinfo_endpoint'] as $key) {
                if (! is_string($document[$key] ?? null)) {
                    throw new RuntimeException("OIDC discovery at {$url} has no {$key}.");
                }
            }

            return $document;
        });
    }
}
