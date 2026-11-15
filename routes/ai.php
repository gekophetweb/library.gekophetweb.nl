<?php

use App\Mcp\Servers\AppearanceServer;
use Laravel\Mcp\Facades\Mcp;

// MCP servers (laravel/mcp). Under /api so they stay reachable without a Cloudflare Access
// login, like the phone app; signed in with the same Sanctum tokens (Settings › Phone app).
Mcp::web('/api/mcp/appearance', AppearanceServer::class)
    ->middleware(['auth:sanctum', 'throttle:60,1']);
