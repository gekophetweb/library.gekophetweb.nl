<?php

namespace App\Enums;

/**
 * The kinds of SSO provider an admin can set up. A closed set: each case is code
 * (see SocialiteSsoDriverFactory). Microsoft and generic OIDC share one OIDC client.
 */
enum SsoDriver: string
{
    case Google = 'google';
    case Microsoft = 'microsoft';
    case Github = 'github';
    case Oidc = 'oidc';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::Microsoft => 'Microsoft (Entra ID)',
            self::Github => 'GitHub',
            self::Oidc => 'OpenID Connect (Authentik, Keycloak, …)',
        };
    }

    /** Driver-specific settings the admin must fill in. */
    public function requiredSettings(): array
    {
        return match ($this) {
            self::Microsoft => ['tenant'],
            self::Oidc => ['issuer'],
            default => [],
        };
    }
}
