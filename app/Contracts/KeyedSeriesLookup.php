<?php

namespace App\Contracts;

use App\Enums\ApiKeyProvider;

/**
 * A series provider that only answers with the user's own API key (guidelines §5.1).
 * SeriesLookupManager hands it the key through withKey() and skips it — for resolve()
 * and volumes() alike — when the user has none.
 */
interface KeyedSeriesLookup extends SeriesLookup
{
    public function requiredKey(): ApiKeyProvider;

    /** A copy of this provider that sends $key. */
    public function withKey(string $key): static;
}
