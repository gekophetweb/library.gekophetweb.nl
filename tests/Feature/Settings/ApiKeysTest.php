<?php

use App\Enums\ApiKeyProvider;
use App\Models\User;
use App\Models\UserApiKey;
use Illuminate\Support\Facades\DB;

it('stores a provider key encrypted and never shows it back', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('settings.api-keys.update', 'rakuten'), ['key' => 'app-id-123'])
        ->assertRedirect(route('settings.api-keys'));

    expect($user->apiKey(ApiKeyProvider::Rakuten))->toBe('app-id-123')
        ->and(DB::table('user_api_keys')->value('key'))->not->toContain('app-id-123');

    $this->get(route('settings.api-keys'))
        ->assertSee('Rakuten')
        ->assertSee('Key saved')
        ->assertDontSee('app-id-123');
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

    $this->put(route('settings.api-keys.update', 'rakuten'), ['key' => ''])->assertSessionHasErrors('key');
    expect(UserApiKey::count())->toBe(0);
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
