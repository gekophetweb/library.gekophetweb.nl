<?php

namespace App\Services\Auth;

use App\Models\SsoProvider;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Decides who an SSO login belongs to, and links identities to accounts. */
class SsoLogin
{
    /**
     * The user to log in for $profile at $provider: a linked identity, else an account
     * with the same verified e-mail (which gets linked), else a new account when the
     * provider allows sign-up. Null means: refuse the login.
     */
    public function resolve(SsoProvider $provider, SsoProfile $profile): ?User
    {
        return DB::transaction(function () use ($provider, $profile) {
            $identity = $provider->identities()->where('subject', $profile->subject)->first();

            if ($identity !== null) {
                $identity->update(['email' => $profile->email]);

                return $identity->user;
            }

            if (! $profile->emailVerified) {
                return null;
            }

            $user = User::query()->where('email', $profile->email)->first();

            if ($user === null) {
                if (! $provider->allow_registration) {
                    return null;
                }

                $user = User::query()->create([
                    'email' => $profile->email,
                    'name' => $profile->name ?: Str::before((string) $profile->email, '@'),
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $this->link($user, $provider, $profile);

            return $user;
        });
    }

    /** Links $profile to $user. False when that identity already belongs to someone else. */
    public function link(User $user, SsoProvider $provider, SsoProfile $profile): bool
    {
        $identity = UserIdentity::query()->firstOrCreate(
            ['sso_provider_id' => $provider->id, 'subject' => $profile->subject],
            ['user_id' => $user->id, 'email' => $profile->email],
        );

        return $identity->user_id === $user->id;
    }
}
