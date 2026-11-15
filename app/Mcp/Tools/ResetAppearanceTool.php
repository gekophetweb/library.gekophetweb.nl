<?php

namespace App\Mcp\Tools;

use App\Support\Appearance\AppearancePhotos;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('reset-appearance')]
#[IsDestructive]
#[IsIdempotent]
#[Description('Restore the standard look for the signed-in user and delete their background photo.')]
class ResetAppearanceTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        app(AppearancePhotos::class)->reset($request->user());

        return AppearanceToolResponse::for($request->user(), 'Back to the standard look; the photo is deleted.');
    }
}
