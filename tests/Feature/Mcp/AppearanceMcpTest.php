<?php

use App\Mcp\Servers\AppearanceServer;
use App\Mcp\Tools\GetAppearanceTool;
use App\Mcp\Tools\ResetAppearanceTool;
use App\Mcp\Tools\SetBackgroundPhotoTool;
use App\Mcp\Tools\UpdateAppearanceTool;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
});

function pngBase64(int $width = 300, int $height = 200): string
{
    return base64_encode(UploadedFile::fake()->image('photo.png', $width, $height)->get());
}

it('lists the four appearance tools', function () {
    AppearanceServer::actingAs($this->user)->tools()
        ->assertRegistered([GetAppearanceTool::class, UpdateAppearanceTool::class, SetBackgroundPhotoTool::class, ResetAppearanceTool::class]);
});

it('returns the current settings and the allowed values', function () {
    $this->user->forceFill(['appearance' => ['accent' => 'teal']])->save();

    AppearanceServer::actingAs($this->user)->tool(GetAppearanceTool::class)
        ->assertOk()
        ->assertStructuredContentPath('settings.accent', 'teal')
        ->assertStructuredContentPath('settings.hasPhoto', false)
        ->assertStructuredContentPath('options.veil.min', 60)
        ->assertSee('Stamp red');
});

it('changes only the options it is given', function () {
    $this->user->forceFill(['appearance' => ['accent' => 'plum']])->save();

    AppearanceServer::actingAs($this->user)->tool(UpdateAppearanceTool::class, ['mode' => 'dark', 'background' => 'grid', 'density' => 'compact'])
        ->assertOk()
        ->assertStructuredContentPath('settings.mode', 'dark');

    expect($this->user->fresh()->appearance->toArray())->toMatchArray([
        'mode' => 'dark', 'accent' => 'plum', 'background' => 'grid', 'density' => 'compact',
    ]);
});

it('refuses values outside the presets', function (array $arguments) {
    AppearanceServer::actingAs($this->user)->tool(UpdateAppearanceTool::class, $arguments)->assertHasErrors();

    expect($this->user->fresh()->appearance->isDefault())->toBeTrue();
})->with([
    'free colour' => [['accent' => '#00ff00']],
    'unknown background' => [['background' => 'stars']],
    'veil too low' => [['veil' => 10]],
    'nothing to change' => [[]],
    'photo without a photo' => [['background' => 'photo']],
]);

it('uploads a background photo from base64 and switches to it', function () {
    AppearanceServer::actingAs($this->user)->tool(SetBackgroundPhotoTool::class, ['image' => 'data:image/png;base64,'.pngBase64(), 'veil' => 90])
        ->assertOk()
        ->assertStructuredContentPath('settings.background', 'photo')
        ->assertStructuredContentPath('settings.hasPhoto', true)
        ->assertStructuredContentPath('settings.veil', 90);

    $photo = $this->user->fresh()->appearance->photo;
    Storage::disk('local')->assertExists($photo);
});

it('refuses data that is not an allowed image', function (string $image) {
    AppearanceServer::actingAs($this->user)->tool(SetBackgroundPhotoTool::class, ['image' => $image])->assertHasErrors();

    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'not base64' => ['%%% not base64 %%%'],
    'text' => [base64_encode('hello, this is not an image')],
    'gif' => [fn () => base64_encode(UploadedFile::fake()->image('a.gif')->get())],
]);

it('resets to the standard look and deletes the photo', function () {
    AppearanceServer::actingAs($this->user)->tool(SetBackgroundPhotoTool::class, ['image' => pngBase64()]);
    expect(Storage::disk('local')->allFiles())->toHaveCount(1);

    AppearanceServer::actingAs($this->user)->tool(ResetAppearanceTool::class)
        ->assertOk()
        ->assertStructuredContentPath('settings.background', 'ruled');

    expect(Storage::disk('local')->allFiles())->toBe([])
        ->and($this->user->fresh()->appearance->isDefault())->toBeTrue();
});

it('needs a Sanctum token over HTTP', function () {
    $initialize = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => [
        'protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'test', 'version' => '1'],
    ]];

    $this->postJson('/api/mcp/appearance', $initialize)->assertUnauthorized();

    $token = $this->user->createToken('assistant')->plainTextToken;
    $this->withToken($token)->postJson('/api/mcp/appearance', $initialize, ['Accept' => 'application/json, text/event-stream'])
        ->assertOk()
        ->assertJsonPath('result.serverInfo.name', 'Library appearance');
});
