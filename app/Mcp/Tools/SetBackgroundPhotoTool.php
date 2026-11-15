<?php

namespace App\Mcp\Tools;

use App\Support\Appearance\Appearance;
use App\Support\Appearance\AppearancePhotos;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * The image comes in the call itself: the server never downloads a URL on the model's
 * behalf, so it cannot be pointed at internal addresses.
 */
#[Name('set-background-photo')]
#[Description('Upload a background photo for the signed-in user (base64, JPG/PNG/WebP, at most 4 MB) and switch the background to it. Replaces any earlier photo.')]
class SetBackgroundPhotoTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            // base64 grows data by a third; the decoded size is checked by AppearancePhotos.
            'image' => ['required', 'string', 'max:'.(int) ceil(AppearancePhotos::MAX_KILOBYTES * 1024 * 4 / 3) + 4],
            'veil' => ['sometimes', 'integer', 'between:'.Appearance::VEIL_MIN.','.Appearance::VEIL_MAX],
        ]);

        // Accept a data URL too ("data:image/png;base64,…").
        $encoded = preg_replace('/^data:[^,]*,/', '', $validated['image']);
        $contents = base64_decode((string) $encoded, true);

        if ($contents === false || $contents === '') {
            return Response::error('image must be base64-encoded image data.');
        }

        try {
            app(AppearancePhotos::class)->replace($request->user(), $contents, $validated['veil'] ?? null);
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        return AppearanceToolResponse::for($request->user(), 'Photo saved and set as the background.');
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'image' => $schema->string()->required()
                ->description('The image file as base64 (a data: URL is fine). JPG, PNG or WebP, at most 4 MB; scaled down to 2400 px.'),
            'veil' => $schema->integer()->min(Appearance::VEIL_MIN)->max(Appearance::VEIL_MAX)
                ->description('Optional: percent of theme colour over the photo so text stays readable.'),
        ];
    }
}
