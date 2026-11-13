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
 * back by LibraryLinks). Pages under /s never act, signed in or not; the signed-in user is
 * read only to check a personal share's recipient (plan share-with-account).
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

        // A personal share: a guest signs in first and comes back here; any other account
        // gets the same 404 as an unknown token.
        if ($share->isPersonal() && $request->user() === null) {
            return redirect()->guest(route('login'));
        }

        abort_unless($share->admits($request->user()), 404);

        $request->attributes->set(self::ATTRIBUTE, $share);
        URL::defaults(['shareToken' => $token]);
        // The token is a route parameter, not a controller argument.
        $request->route()->forgetParameter('shareToken');

        return $next($request);
    }
}
