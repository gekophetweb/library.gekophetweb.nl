<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetAppearanceTool;
use App\Mcp\Tools\ResetAppearanceTool;
use App\Mcp\Tools\SetBackgroundPhotoTool;
use App\Mcp\Tools\UpdateAppearanceTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * The appearance MCP server (plan redesign-themes §5): lets an AI assistant read and set
 * the signed-in user's own look, the same options as Settings › Appearance. Reached at
 * /api/mcp/appearance with a Sanctum token from Settings › Phone app (routes/ai.php).
 */
#[Name('Library appearance')]
#[Version('1.0.0')]
#[Instructions(<<<'TEXT'
    Styles one person's view of their Gek op het Web library (a catalogue of their books, music
    and games). Only they see these settings; share links always use the standard look.

    Start with get-appearance: it returns the current settings and every allowed value. Options:
    - mode: system (follow the device), light or dark.
    - accent: one of six preset colours; there is no free colour, each preset has a light and a
      dark shade checked for contrast.
    - background: ruled (index card, the default), plain, dots, grid (graph paper) or photo.
    - veil: 60-95, how much theme colour covers a photo so text stays readable.
    - headingFont: bricolage, fraunces or instrument. density: comfortable or compact.

    To use a picture, call set-background-photo with the image as base64 (JPG, PNG or WebP, at
    most 4 MB); it switches the background to photo. reset-appearance restores the standard
    look and deletes the photo. Changes show on the person's next page load.
    TEXT)]
class AppearanceServer extends Server
{
    protected array $tools = [
        GetAppearanceTool::class,
        UpdateAppearanceTool::class,
        SetBackgroundPhotoTool::class,
        ResetAppearanceTool::class,
    ];
}
