<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get-appearance')]
#[IsReadOnly]
#[Description('Get the current appearance settings of the signed-in user and every allowed value for each option.')]
class GetAppearanceTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        return AppearanceToolResponse::for($request->user());
    }
}
