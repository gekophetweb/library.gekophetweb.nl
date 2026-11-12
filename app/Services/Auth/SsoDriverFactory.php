<?php

namespace App\Services\Auth;

use App\Models\SsoProvider;
use Laravel\Socialite\Contracts\Provider;

/** Turns a configured SsoProvider row into a Socialite driver. */
interface SsoDriverFactory
{
    public function for(SsoProvider $provider): Provider;
}
