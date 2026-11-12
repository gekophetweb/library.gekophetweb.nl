<?php

namespace App\Services\Auth;

use App\Mail\LoginCodeMail;
use App\Models\LoginCode;
use App\Models\User;
use App\Support\Auth\LoginSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Login by a single-use code sent by e-mail. Any address may request one; an account for
 * a new address is only created once its code is redeemed.
 */
class EmailLoginCodes
{
    public const int MINUTES_VALID = 10;

    public static function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    /**
     * Sends a fresh code to $email, replacing any earlier one. Silently does nothing for a
     * new address when sign-up is closed, so the caller's response never reveals which
     * addresses have an account.
     */
    public function send(string $email, ?string $ip): void
    {
        $email = self::normalizeEmail($email);

        if (! LoginSettings::emailRegistrationEnabled() && ! User::query()->where('email', $email)->exists()) {
            return;
        }

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        $loginCode = DB::transaction(function () use ($email, $code, $ip) {
            LoginCode::query()->where('email', $email)->whereNull('used_at')->delete();

            return LoginCode::query()->create([
                'email' => $email,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(self::MINUTES_VALID),
                'ip' => $ip,
            ]);
        });

        $link = URL::temporarySignedRoute('login.code.link', $loginCode->expires_at, ['loginCode' => $loginCode->id]);

        Mail::to($email)->queue(new LoginCodeMail($code, $link, self::MINUTES_VALID));
    }

    /** The open login code for $email if $code matches it. Counts the attempt either way. */
    public function check(string $email, string $code): ?LoginCode
    {
        $loginCode = LoginCode::query()
            ->where('email', self::normalizeEmail($email))
            ->whereNull('used_at')
            ->latest('id')
            ->first();

        if ($loginCode === null || ! $loginCode->isUsable()) {
            return null;
        }

        $loginCode->increment('attempts');

        return Hash::check($code, $loginCode->code_hash) ? $loginCode : null;
    }

    /**
     * Uses up $loginCode and returns the user to log in, creating a verified account for a
     * new address. Null when the code is no longer open, or the address has no account and
     * sign-up is closed. The second element is true for a newly created account.
     *
     * @return array{0: User, 1: bool}|null
     */
    public function redeem(LoginCode $loginCode): ?array
    {
        return DB::transaction(function () use ($loginCode) {
            $loginCode = LoginCode::query()->lockForUpdate()->find($loginCode->id);

            if ($loginCode === null || $loginCode->used_at !== null || $loginCode->expires_at->isPast()) {
                return null;
            }

            $loginCode->forceFill(['used_at' => now()])->save();

            $user = User::query()->where('email', $loginCode->email)->first();
            $created = false;

            if ($user === null) {
                if (! LoginSettings::emailRegistrationEnabled()) {
                    return null;
                }

                $user = User::query()->create([
                    'email' => $loginCode->email,
                    'name' => Str::before($loginCode->email, '@'),
                ]);
                $created = true;
            }

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            return [$user, $created];
        });
    }
}
