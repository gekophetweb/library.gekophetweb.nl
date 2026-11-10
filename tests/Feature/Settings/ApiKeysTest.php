<?php

use App\Enums\ApiKeyProvider;
use App\Models\User;
use App\Models\UserApiKey;
use Illuminate\Support\Facades\DB;

it('stores a provider key encrypted and never shows it back', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('settings.api-keys.update', 'rakuten'), ['key' => 'app-id-123'])
        ->assertRedirect(route('settings.edit'));

    expect($user->apiKey(ApiKeyProvider::Rakuten))->toBe('app-id-123')
        ->and(DB::table('user_api_keys')->value('key'))->not->toContain('app-id-123');

    $this->get(route('settings.edit'))
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

    $this->delete(route('settings.api-keys.destroy', 'google_books'))->assertRedirect(route('settings.edit'));
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
