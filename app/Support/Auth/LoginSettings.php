<?php

namespace App\Support\Auth;

use App\Models\AppSetting;

/** The login switches an admin controls. Both default to on. */
final class LoginSettings
{
    public const string EMAIL_CODES = 'auth.email_codes_enabled';

    public const string EMAIL_REGISTRATION = 'auth.email_registration_enabled';

    public static function emailCodesEnabled(): bool
    {
        return (bool) AppSetting::get(self::EMAIL_CODES, true);
    }

    /** May an e-mail without an account sign up with a login code? */
    public static function emailRegistrationEnabled(): bool
    {
        return (bool) AppSetting::get(self::EMAIL_REGISTRATION, true);
    }
}
