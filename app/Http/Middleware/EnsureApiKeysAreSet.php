<?php

namespace App\Http\Middleware;

use App\Enums\ApiKeyProvider;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Scanning starts only once the user has saved every key it needs (§5.1), so a scan
 * never runs with most of its lookups skipped. Optional keys (Rakuten) don't count:
 * lookups skip that provider instead. Sends them to settings otherwise.
 */
class EnsureApiKeysAreSet
{
    public function handle(Request $request, Closure $next): Response
    {
        $missing = $request->user()->missingScanKeys();

        if ($missing === []) {
            return $next($request);
        }

        $names = implode(', ', array_map(fn (ApiKeyProvider $provider) => $provider->label(), $missing));

        return redirect()->route('settings.api-keys')
            ->withErrors(['api_keys' => "Add your API keys before scanning. Still missing: {$names}."]);
    }
}
