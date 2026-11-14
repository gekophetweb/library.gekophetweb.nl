<?php

namespace App\Http\Middleware;

use App\Enums\ApiKeyProvider;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Scanning starts only once the user has saved a key for every provider (§5.1), so a
 * scan never runs with half its lookups skipped. Sends them to settings otherwise.
 */
class EnsureApiKeysAreSet
{
    public function handle(Request $request, Closure $next): Response
    {
        $missing = $request->user()->missingApiKeys();

        if ($missing === []) {
            return $next($request);
        }

        $names = implode(', ', array_map(fn (ApiKeyProvider $provider) => $provider->label(), $missing));

        return redirect()->route('settings.api-keys')
            ->withErrors(['api_keys' => "Add your API keys before scanning. Still missing: {$names}."]);
    }
}
