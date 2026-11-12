<?php

namespace App\Services\Auth;

use App\Enums\SsoDriver;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/** What an SSO login told us about the person, normalized across drivers. */
final readonly class SsoProfile
{
    public function __construct(
        public string $subject,
        public ?string $email,
        public bool $emailVerified,
        public ?string $name,
    ) {}

    public static function from(SsoDriver $driver, SocialiteUser $user): self
    {
        $email = $user->getEmail() ? Str::lower(trim($user->getEmail())) : null;
        $raw = method_exists($user, 'getRaw') ? $user->getRaw() : [];

        $verified = $email !== null && match ($driver) {
            // Socialite only returns GitHub's primary, verified address.
            SsoDriver::Github => true,
            SsoDriver::Google => filter_var($raw['email_verified'] ?? $raw['verified_email'] ?? false, FILTER_VALIDATE_BOOL),
            // Microsoft sends no email_verified claim, so its addresses never count as verified.
            SsoDriver::Microsoft, SsoDriver::Oidc => ($raw['email_verified'] ?? false) === true,
        };

        return new self((string) $user->getId(), $email, $verified, $user->getName() ?: $user->getNickname());
    }
}
