<?php

namespace App\Mcp\Tools;

use App\Support\Appearance\AccentColour;
use App\Support\Appearance\Appearance;
use App\Support\Appearance\Background;
use App\Support\Appearance\Density;
use App\Support\Appearance\HeadingFont;
use App\Support\Appearance\ThemeMode;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update-appearance')]
#[IsIdempotent]
#[Description('Change one or more appearance options of the signed-in user. Options you leave out keep their value. To use a photo background, upload it with set-background-photo first.')]
class UpdateAppearanceTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $changes = $request->validate(Appearance::rules(), [
            'veil.between' => 'veil must be between :min and :max.',
        ]);

        if ($changes === []) {
            return Response::error('Pass at least one option to change: '.implode(', ', Appearance::SETTABLE).'.');
        }

        $user = $request->user();

        if (($changes['background'] ?? null) === Background::Photo->value && $user->appearance->photo === null) {
            return Response::error('There is no photo yet. Upload one with set-background-photo; it switches the background to photo.');
        }

        $user->forceFill(['appearance' => $user->appearance->with($changes)])->save();

        return AppearanceToolResponse::for($user, 'Saved. The new look shows on the next page load.');
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        $values = fn (string $enum): array => array_column($enum::cases(), 'value');

        return [
            'mode' => $schema->string()->enum($values(ThemeMode::class))
                ->description('system follows the device; light or dark always uses that theme.'),
            'accent' => $schema->string()->enum($values(AccentColour::class))
                ->description('Accent colour preset: stamp (red, the default), teal, indigo, plum, mustard or forest.'),
            'background' => $schema->string()->enum($values(Background::class))
                ->description('ruled (index card), plain, dots, grid (graph paper) or photo (needs an uploaded photo).'),
            'veil' => $schema->integer()->min(Appearance::VEIL_MIN)->max(Appearance::VEIL_MAX)
                ->description('For a photo: percent of theme colour over it so text stays readable (default '.Appearance::VEIL_DEFAULT.').'),
            'headingFont' => $schema->string()->enum($values(HeadingFont::class))
                ->description('Font for headings: bricolage (default), fraunces (a serif) or instrument (same as the body).'),
            'density' => $schema->string()->enum($values(Density::class))
                ->description('compact makes everything a little smaller and tighter.'),
        ];
    }
}
