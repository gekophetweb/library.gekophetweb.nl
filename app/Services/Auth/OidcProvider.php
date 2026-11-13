<?php

namespace App\Services\Auth;

use GuzzleHttp\RequestOptions;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;

/** A generic OpenID Connect client whose endpoints come from the issuer's discovery document. */
class OidcProvider extends AbstractProvider
{
    protected $scopes = ['openid', 'email', 'profile'];

    protected $scopeSeparator = ' ';

    protected $usesPKCE = true;

    /** @var array<string, mixed> */
    private array $discovery = [];

    /** @param  array<string, mixed>  $discovery */
    public function useDiscovery(array $discovery): static
    {
        $this->discovery = $discovery;

        return $this;
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->discovery['authorization_endpoint'], $state);
    }

    protected function getTokenUrl(): string
    {
        return $this->discovery['token_endpoint'];
    }

    /** @return array<string, mixed> */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get($this->discovery['userinfo_endpoint'], [
            RequestOptions::HEADERS => ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'],
        ]);

        return json_decode((string) $response->getBody(), true) ?? [];
    }

    /** @param  array<string, mixed>  $user */
    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id' => $user['sub'] ?? null,
            'nickname' => $user['preferred_username'] ?? null,
            'name' => $user['name'] ?? $user['preferred_username'] ?? null,
            'email' => $user['email'] ?? null,
            'avatar' => $user['picture'] ?? null,
        ]);
    }
}
