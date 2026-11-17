<?php

namespace App\Services;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Sleep;

/**
 * Keeps the whole app to a few NDL Search calls a minute (config series.providers.ndl
 * .per_minute). The budget is shared by everyone: NDL sees every call from this server.
 *
 * While a queued job runs (see AppServiceProvider), a call over the budget waits for a
 * free slot. Anywhere else (a page request) it is skipped, so a page never hangs.
 */
class NdlRateLimiter
{
    private const string KEY = 'ndl-search';

    private bool $mayWait = false;

    public function __construct(private readonly RateLimiter $limiter) {}

    /** Whether over-budget calls wait (in a queued job) instead of being skipped. */
    public function waitWhenLimited(bool $wait = true): void
    {
        $this->mayWait = $wait;
    }

    /** Takes a slot for one NDL call; false when the call must be skipped. */
    public function acquire(): bool
    {
        $max = (int) config('series.providers.ndl.per_minute');

        if ($max <= 0) {
            return true; // 0 = no limit
        }

        while ($this->limiter->tooManyAttempts(self::KEY, $max)) {
            if (! $this->mayWait) {
                return false;
            }

            Sleep::for(max(1, $this->limiter->availableIn(self::KEY)))->seconds();
        }

        $this->limiter->hit(self::KEY, 60);

        return true;
    }
}
