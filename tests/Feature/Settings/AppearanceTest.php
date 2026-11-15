<?php

use App\Models\LibraryShare;
use App\Models\User;
use App\Support\Appearance\Appearance;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
});

it('shows the standard look until a user sets their own', function () {
    expect($this->user->appearance->isDefault())->toBeTrue();

    $this->actingAs($this->user)->get(route('settings.appearance'))
        ->assertOk()
        ->assertSee('data-theme-mode="system"', false)
        ->assertSee('data-accent="stamp"', false)
        ->assertSee('data-bg="ruled"', false)
        ->assertSee(url('api/mcp/appearance'));

    expect($this->user->fresh()->getRawOriginal('appearance'))->toBeNull();
});

it('saves each option and puts it on every page', function (string $key, string|int $value, string $attribute) {
    $this->actingAs($this->user)
        ->put(route('settings.appearance.update'), [$key => $value])
        ->assertRedirect(route('settings.appearance'))
        ->assertSessionHasNoErrors();

    expect($this->user->fresh()->appearance->toArray()[$key])->toBe($value);

    $this->get(route('home'))->assertOk()->assertSee($attribute, false);
})->with([
    'theme' => ['mode', 'dark', 'data-theme="dark"'],
    'accent' => ['accent', 'teal', 'data-accent="teal"'],
    'background' => ['background', 'dots', 'data-bg="dots"'],
    'heading font' => ['headingFont', 'fraunces', 'data-font="fraunces"'],
    'density' => ['density', 'compact', 'data-density="compact"'],
    'veil' => ['veil', 70, 'data-bg="ruled"'],
]);

it('rejects values that are not one of the presets', function (string $key, mixed $value) {
    $this->actingAs($this->user)
        ->from(route('settings.appearance'))
        ->put(route('settings.appearance.update'), [$key => $value])
        ->assertSessionHasErrors($key);

    expect($this->user->fresh()->appearance->isDefault())->toBeTrue();
})->with([
    ['mode', 'sepia'],
    ['accent', '#ff00ff'],
    ['background', 'stars'],
    ['veil', 30],
    ['veil', 100],
    ['headingFont', 'comic-sans'],
    ['density', 'tiny'],
]);

it('needs a photo before the photo background can be picked', function () {
    $this->actingAs($this->user)
        ->put(route('settings.appearance.update'), ['background' => 'photo'])
        ->assertSessionHasErrors('photo');
});

it('stores an uploaded photo privately, scaled down, and shows it behind a veil', function () {
    $this->actingAs($this->user)
        ->put(route('settings.appearance.update'), [
            'background' => 'photo',
            'veil' => 75,
            'photo' => UploadedFile::fake()->image('shelf.jpg', 3600, 1800),
        ])
        ->assertSessionHasNoErrors();

    $appearance = $this->user->fresh()->appearance;
    expect($appearance->background->value)->toBe('photo')
        ->and($appearance->veil)->toBe(75)
        ->and($appearance->photo)->toStartWith('appearance/'.$this->user->id.'/');

    Storage::disk('local')->assertExists($appearance->photo);
    [$width, $height] = getimagesizefromstring(Storage::disk('local')->get($appearance->photo));
    expect([$width, $height])->toBe([2400, 1200]);

    $this->get(route('home'))
        ->assertSee('data-bg="photo"', false)
        ->assertSee('--veil: 75%', false)
        ->assertSee('appearance/photo/'.$this->user->id, false);
});

it('rejects photos that are not JPG, PNG or WebP, or too big', function (UploadedFile $file) {
    $this->actingAs($this->user)
        ->put(route('settings.appearance.update'), ['background' => 'photo', 'photo' => $file])
        ->assertSessionHasErrors('photo');

    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'gif' => fn () => UploadedFile::fake()->image('shelf.gif'),
    'pdf' => fn () => UploadedFile::fake()->create('shelf.pdf', 10, 'application/pdf'),
    'too big' => fn () => UploadedFile::fake()->image('shelf.jpg')->size(5000),
]);

it('replaces the old photo and deletes it when going back to the standard look', function () {
    $this->actingAs($this->user)->put(route('settings.appearance.update'), ['background' => 'photo', 'photo' => UploadedFile::fake()->image('a.png', 400, 300)]);
    $first = $this->user->fresh()->appearance->photo;

    $this->put(route('settings.appearance.update'), ['background' => 'photo', 'photo' => UploadedFile::fake()->image('b.png', 400, 300)]);
    $second = $this->user->fresh()->appearance->photo;

    Storage::disk('local')->assertMissing($first);
    Storage::disk('local')->assertExists($second);

    $this->delete(route('settings.appearance.reset'))->assertRedirect(route('settings.appearance'));

    Storage::disk('local')->assertMissing($second);
    expect($this->user->fresh()->appearance->isDefault())->toBeTrue();
});

it('serves the photo only with a valid signature and only to its owner', function () {
    $this->actingAs($this->user)->put(route('settings.appearance.update'), ['background' => 'photo', 'photo' => UploadedFile::fake()->image('a.png', 400, 300)]);
    $signed = $this->user->fresh()->appearance->photoUrl($this->user);

    $this->get($signed)->assertOk()->assertHeader('Cache-Control', 'immutable, max-age=31536000, private');
    $this->get(route('appearance.photo', ['user' => $this->user, 'v' => 'x']))->assertForbidden();

    $this->actingAs(User::factory()->create())->get($signed)->assertNotFound();
    auth()->logout();
    $this->get($signed)->assertRedirect(route('login'));
});

it('saves the sun and moon button choice', function () {
    $this->actingAs($this->user)
        ->putJson(route('settings.appearance.theme'), ['mode' => 'dark'])
        ->assertOk()->assertJson(['mode' => 'dark']);

    expect($this->user->fresh()->appearance->mode->value)->toBe('dark');

    $this->from(route('home'))->put(route('settings.appearance.theme'), ['mode' => 'light'])->assertRedirect(route('home'));
    expect($this->user->fresh()->appearance->mode->value)->toBe('light');

    $this->putJson(route('settings.appearance.theme'), ['mode' => 'purple'])->assertUnprocessable();
});

it('falls back to the defaults for unknown stored values', function () {
    $appearance = Appearance::fromArray(['mode' => 'neon', 'veil' => 500, 'background' => 'photo', 'accent' => 'plum']);

    expect($appearance->mode->value)->toBe('system')
        ->and($appearance->veil)->toBe(Appearance::VEIL_MAX)
        ->and($appearance->background->value)->toBe('ruled')
        ->and($appearance->accent->value)->toBe('plum');
});

it('shows share links in the standard look, never the visitor’s or the owner’s', function () {
    $this->seed(CatalogueSeeder::class);
    $owner = User::factory()->create(['appearance' => ['mode' => 'dark', 'accent' => 'forest', 'background' => 'grid']]);
    $this->user->forceFill(['appearance' => ['mode' => 'light', 'accent' => 'plum', 'background' => 'dots']])->save();
    $share = LibraryShare::factory()->for($owner)->create();

    $this->actingAs($this->user)->get(route('share.index', ['shareToken' => $share->token]))
        ->assertOk()
        ->assertSee('data-theme-mode="system"', false)
        ->assertSee('data-accent="stamp"', false)
        ->assertSee('data-bg="ruled"', false)
        ->assertDontSee(' data-guest ', false)
        ->assertDontSee('plum')
        ->assertDontSee('forest');
});

it('deletes the photos together with the account', function () {
    $this->actingAs($this->user)->put(route('settings.appearance.update'), ['background' => 'photo', 'photo' => UploadedFile::fake()->image('a.png', 400, 300)]);
    expect(Storage::disk('local')->allFiles())->toHaveCount(1);

    $this->delete(route('settings.account.destroy'), ['confirm_email' => $this->user->email])->assertRedirect(route('login'));

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('keeps the signed photo URL valid until the photo changes', function () {
    URL::forceRootUrl('https://library.example.test');
    $appearance = Appearance::fromArray(['background' => 'photo', 'photo' => 'appearance/1/a.webp']);

    expect($appearance->photoUrl($this->user))->toBe($appearance->photoUrl($this->user))
        ->and($appearance->with(['photo' => 'appearance/1/b.webp'])->photoUrl($this->user))->not->toBe($appearance->photoUrl($this->user));
});
