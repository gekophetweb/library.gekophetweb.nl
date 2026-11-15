<?php

use App\Enums\ApiKeyProvider;
use App\Models\ApiCallLog;
use App\Models\User;
use App\Models\UserApiKey;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
 * A key is tried at its provider before it is saved: a key the provider refuses is not
 * saved; a provider that can't be reached doesn't block saving, but says so.
 * Answers come from tests/Fixtures/api-keys and tests/Fixtures/catalogue.
 */

function apiKeyFixture(string $file): string
{
    return file_get_contents(base_path("tests/Fixtures/{$file}"));
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('saves a Rakuten key that the provider accepts, without logging the key', function () {
    Http::fake(['openapi.rakuten.co.jp/*' => Http::response(apiKeyFixture('catalogue/rakuten-isbn-9784592135746.json'))]);

    $this->put(route('settings.api-keys.update', 'rakuten'), ['key' => ' app-id-123 ', 'secret' => 'pk_access-456'])
        ->assertRedirect(route('settings.api-keys'))
        ->assertSessionHas('status', 'Rakuten Books key checked and saved.');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'applicationId=app-id-123')
        && str_contains($request->url(), 'accessKey=pk_access-456'));
    expect($this->user->apiKey(ApiKeyProvider::Rakuten))->toBe('app-id-123');

    $log = ApiCallLog::sole();
    expect($log->provider)->toBe('rakuten')
        ->and($log->operation)->toBe('key-check')
        ->and($log->outcome)->toBe(ApiCallLog::OUTCOME_SUCCESS)
        ->and($log->user_id)->toBe($this->user->id)
        ->and($log->query)->not->toContain('app-id-123')
        ->and($log->query)->not->toContain('pk_access-456');
});

it('does not save a key the provider refuses, and keeps the saved one', function (string $provider, array $form, string $url, int $status, string $fixture, string $message) {
    UserApiKey::factory()->for($this->user)->create(['provider' => ApiKeyProvider::from($provider), 'key' => 'old-key', 'secret' => $form['secret'] ?? null]);
    Http::fake([$url => Http::response(apiKeyFixture($fixture), $status)]);

    $this->from(route('settings.api-keys'))
        ->put(route('settings.api-keys.update', $provider), $form)
        ->assertRedirect(route('settings.api-keys'))
        ->assertSessionHasErrors(['key' => ApiKeyProvider::from($provider)->label()." did not accept this key: {$message}"]);

    expect($this->user->apiKey(ApiKeyProvider::from($provider)))->toBe('old-key');

    // The settings page shows the provider's reason.
    $this->from(route('settings.api-keys'))->followingRedirects()
        ->put(route('settings.api-keys.update', $provider), $form)
        ->assertSee($message);
})->with([
    'Rakuten' => ['rakuten', ['key' => 'wrong', 'secret' => 'pk_wrong'], 'openapi.rakuten.co.jp/*', 400, 'api-keys/rakuten-wrong-application-id.json', 'specify valid applicationId'],
    'Rakuten access key' => ['rakuten', ['key' => 'app-id', 'secret' => 'pk_wrong'], 'openapi.rakuten.co.jp/*', 403, 'api-keys/rakuten-wrong-application-id.json', 'specify valid applicationId'],
    'Google Books' => ['google_books', ['key' => 'wrong'], 'www.googleapis.com/*', 400, 'api-keys/googlebooks-invalid-key.json', 'API key not valid. Please pass a valid API key.'],
    'Hardcover (GraphQL error with 200)' => ['hardcover', ['key' => 'wrong'], 'api.hardcover.app/*', 200, 'api-keys/hardcover-invalid-token.json', 'Could not verify JWT: JWSError JWSInvalidSignature'],
]);

it('saves Google Books and Hardcover keys the provider accepts', function () {
    Http::fake([
        'www.googleapis.com/*' => Http::response(apiKeyFixture('catalogue/googlebooks-isbn-9780593820247.json')),
        'api.hardcover.app/*' => Http::response(apiKeyFixture('api-keys/hardcover-me.json')),
    ]);

    $this->put(route('settings.api-keys.update', 'google_books'), ['key' => 'AIza-good'])
        ->assertSessionHas('status', 'Google Books key checked and saved.');
    // Hardcover shows its token as "Bearer …": the check sends it as it will be used.
    $this->put(route('settings.api-keys.update', 'hardcover'), ['key' => 'Bearer hc-token'])
        ->assertSessionHas('status', 'Hardcover key checked and saved.');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'googleapis.com') && str_contains($request->url(), 'key=AIza-good'));
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'hardcover') && $request->header('Authorization') === ['Bearer hc-token']);
    expect($this->user->apiKey(ApiKeyProvider::GoogleBooks))->toBe('AIza-good')
        ->and($this->user->apiKey(ApiKeyProvider::Hardcover))->toBe('Bearer hc-token')
        ->and(ApiCallLog::query()->where('operation', 'key-check')->count())->toBe(2);
});

it('saves the key anyway when the provider cannot say, and tells the user', function (Closure $answer) {
    Http::fake(['www.googleapis.com/*' => $answer]);

    $this->put(route('settings.api-keys.update', 'google_books'), ['key' => 'AIza-maybe'])
        ->assertRedirect(route('settings.api-keys'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', fn (string $status) => str_starts_with($status, 'Google Books key saved, but it could not be checked'));

    expect($this->user->apiKey(ApiKeyProvider::GoogleBooks))->toBe('AIza-maybe');
})->with([
    'server error' => [fn () => fn () => Http::response('Service Unavailable', 503)],
    'quota used up' => [fn () => fn () => Http::response(apiKeyFixture('catalogue/googlebooks-429.json'), 429)],
    'no connection' => [fn () => fn () => Http::failedConnection()],
]);
