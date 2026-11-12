<?php

namespace App\Http\Middleware;

use App\Models\LibraryShare;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves `{shareToken}` for the read-only share routes. An unknown token and a lapsed
 * one both 404, so a viewer cannot tell them apart. The share goes on the request (read
 * back by LibraryLinks); no user is ever logged in, so nothing under /s can act.
 */
class ResolveLibraryShare
{
    public const string ATTRIBUTE = 'libraryShare';

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->route('shareToken');

        $share = strlen($token) === LibraryShare::TOKEN_LENGTH
            ? LibraryShare::query()->usable()->where('token', $token)->with(['user', 'shareable'])->first()
            : null;

        // hash_equals: the lookup is by index, but make the final comparison constant-time.
        abort_unless($share !== null && hash_equals($share->token, $token), 404);

        $request->attributes->set(self::ATTRIBUTE, $share);
        URL::defaults(['shareToken' => $token]);
        // The token is a route parameter, not a controller argument.
        $request->route()->forgetParameter('shareToken');

        return $next($request);
    }
}
