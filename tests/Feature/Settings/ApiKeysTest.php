<?php

use App\Enums\ApiKeyProvider;
use App\Models\User;
use App\Models\UserApiKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Every key is tried at its provider before saving (ApiKeyCheckTest): accept them all here.
    Http::fake(fn () => Http::response(file_get_contents(base_path('tests/Fixtures/api-keys/hardcover-me.json'))));
});

it('stores a provider key encrypted and never shows it back', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('settings.api-keys.update', 'rakuten'), ['key' => 'app-id-123', 'secret' => ' pk_access-456 '])
        ->assertRedirect(route('settings.api-keys'));

    expect($user->apiKey(ApiKeyProvider::Rakuten))->toBe('app-id-123')
        ->and($user->apiKeySecret(ApiKeyProvider::Rakuten))->toBe('pk_access-456')
        ->and(DB::table('user_api_keys')->value('key'))->not->toContain('app-id-123')
        ->and(DB::table('user_api_keys')->value('secret'))->not->toContain('pk_access-456');

    $this->get(route('settings.api-keys'))
        ->assertSee('Rakuten')
        ->assertSee('Key saved')
        ->assertDontSee('app-id-123')
        ->assertDontSee('pk_access-456');
});

it('replaces and removes a key', function () {
    $user = User::factory()->create();
    UserApiKey::factory()->for($user)->create(['provider' => ApiKeyProvider::GoogleBooks, 'key' => 'old']);

    $this->actingAs($user)->put(route('settings.api-keys.update', 'google_books'), ['key' => 'new']);
    expect($user->apiKey(ApiKeyProvider::GoogleBooks))->toBe('new')
        ->and(UserApiKey::count())->toBe(1);

    $this->delete(route('settings.api-keys.destroy', 'google_books'))->assertRedirect(route('settings.api-keys'));
    expect($user->apiKey(ApiKeyProvider::GoogleBooks))->toBeNull();
});

it('keeps keys per user', function () {
    [$ann, $bob] = User::factory()->count(2)->create();
    UserApiKey::factory()->for($ann)->create(['provider' => ApiKeyProvider::Rakuten, 'key' => 'ann-key']);

    expect($bob->apiKey(ApiKeyProvider::Rakuten))->toBeNull()
        ->and($ann->apiKey(ApiKeyProvider::Rakuten))->toBe('ann-key');
});

it('rejects an unknown provider and an empty key', function () {
    $this->actingAs(User::factory()->create())
        ->put(route('settings.api-keys.update', 'myspace'), ['key' => 'x'])
        ->assertNotFound();

    $this->put(route('settings.api-keys.update', 'rakuten'), ['key' => '', 'secret' => 'pk_x'])->assertSessionHasErrors('key');
    expect(UserApiKey::count())->toBe(0);
});

it('needs Rakuten\'s access key next to the application ID, and no second value elsewhere', function () {
    $this->actingAs(User::factory()->create());

    $this->put(route('settings.api-keys.update', 'rakuten'), ['key' => 'app-id'])->assertSessionHasErrors('secret');
    $this->put(route('settings.api-keys.update', 'hardcover'), ['key' => 'token', 'secret' => 'x'])->assertSessionHasErrors('secret');
    expect(UserApiKey::count())->toBe(0);

    $this->get(route('settings.api-keys.show', 'rakuten'))
        ->assertSee('name="secret"', false)
        ->assertSee('Access key')
        ->assertSee('pk_');
    $this->get(route('settings.api-keys.show', 'hardcover'))->assertDontSee('name="secret"', false);
});

it('treats a Rakuten application ID without access key as missing', function () {
    $user = User::factory()->withApiKeys()->create();
    $user->apiKeys()->where('provider', ApiKeyProvider::Rakuten->value)->update(['secret' => null]);

    expect($user->apiKey(ApiKeyProvider::Rakuten))->toBeNull()
        ->and($user->missingApiKeys())->toBe([ApiKeyProvider::Rakuten]);

    $this->actingAs($user)->get(route('settings.api-keys'))
        ->assertSee('Access key missing')
        ->assertSee(route('settings.api-keys.destroy', 'rakuten'), false);
});

it('shows this server\'s IP address in the Rakuten guide when it is configured', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('settings.api-keys.show', 'rakuten'))->assertDontSee('data-server-ip', false)->assertSee('Ask the person who runs this site');

    config(['catalogue.providers.rakuten.server_ip' => '203.0.113.7']);
    $this->get(route('settings.api-keys.show', 'rakuten'))->assertSee('203.0.113.7');
});

it('splits settings into separate pages, each with the settings menu', function () {
    $user = User::factory()->create();

    foreach (['settings.edit' => 'Profile', 'settings.api-keys' => 'API keys', 'settings.tokens' => 'Phone app tokens',
        'settings.shares' => 'Shared links', 'settings.sign-in' => 'Linked sign-in methods', 'settings.account' => 'Delete my account'] as $route => $text) {
        $this->actingAs($user)->get(route($route))
            ->assertOk()
            ->assertSee($text)
            ->assertSee('aria-current="page"', false)
            ->assertSee(route('settings.account'));
    }

    $this->get(route('settings.edit'))->assertDontSee('Delete my account')->assertDontSee('Create token');
});

it('counts the missing keys in the settings menu', function () {
    $this->actingAs(User::factory()->create())->get(route('settings.edit'))
        ->assertSee('data-missing-key-count>3', false);
});
