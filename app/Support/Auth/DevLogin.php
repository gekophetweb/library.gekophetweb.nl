<?php

namespace App\Support\Auth;

/**
 * The local-only "Sign in as dev user" shortcut (guidelines §2). Needs both the local
 * environment and the explicit flag; neither alone is enough.
 */
final class DevLogin
{
    public static function available(): bool
    {
        return app()->environment('local') && config('auth.dev_sso_bypass') === true;
    }
}
