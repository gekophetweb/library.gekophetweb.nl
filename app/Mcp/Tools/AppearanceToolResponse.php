<?php

namespace App\Mcp\Tools;

use App\Models\User;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

/** What every appearance tool answers with: the settings now in force, plus the options. */
final class AppearanceToolResponse
{
    public static function for(User $user, ?string $message = null): ResponseFactory
    {
        $described = $user->appearance->describe();

        return Response::structured($message === null ? $described : ['message' => $message, ...$described]);
    }
}
