<?php

namespace App\Services;

use App\Enums\ApiKeyProvider;
use App\Support\ApiKeys\ApiKeyCheck;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

/**
 * Tries a user's key at its provider with one small, logged call before it is saved
 * (guidelines §5.1), so a mistyped key shows up in settings instead of as failed lookups
 * later. The key itself never reaches the API call log.
 */
class ApiKeyChecker
{
    /** Any book will do: the call only shows whether the key is accepted. */
    private const string TEST_ISBN = '9784592135746';

    /** Hasura's codes for a token it does not accept (Hardcover answers them with HTTP 200). */
    private const array GRAPHQL_AUTH_ERRORS = ['invalid-jwt', 'invalid-headers', 'access-denied'];

    public function __construct(private readonly Http $http, private readonly ApiCallLogger $logger) {}

    public function check(ApiKeyProvider $provider, string $key, ?string $secret = null): ApiKeyCheck
    {
        try {
            $response = match ($provider) {
                ApiKeyProvider::Rakuten => $this->send($provider, 'isbn='.self::TEST_ISBN, fn () => $this->http->timeout(config('catalogue.timeout'))->acceptJson()
                    ->get(config('catalogue.providers.rakuten.endpoint'), [
                        'format' => 'json', 'formatVersion' => 2, 'isbn' => self::TEST_ISBN, 'hits' => 1,
                        'applicationId' => $key, 'accessKey' => $secret,
                    ])),
                ApiKeyProvider::GoogleBooks => $this->send($provider, 'q=isbn:'.self::TEST_ISBN, fn () => $this->http->timeout(config('catalogue.timeout'))->acceptJson()
                    ->get(config('catalogue.providers.google_books.endpoint'), ['q' => 'isbn:'.self::TEST_ISBN, 'maxResults' => 1, 'key' => $key])),
                ApiKeyProvider::Hardcover => $this->send($provider, 'me', fn () => $this->http->withUserAgent(config('series.user_agent'))
                    ->timeout(config('series.timeout'))->acceptJson()
                    // Hardcover shows the token as "Bearer …"; lookups accept either form too.
                    ->withToken(trim(Str::after(trim($key), 'Bearer ')))
                    ->post(config('series.providers.hardcover.endpoint'), ['query' => '{ me { id } }'])),
            };
        } catch (ConnectionException) {
            return ApiKeyCheck::unknown("{$provider->label()} did not answer.");
        }

        return $this->verdict($provider, $response);
    }

    /** @param  Closure(): Response  $request */
    private function send(ApiKeyProvider $provider, string $query, Closure $request): Response
    {
        return $this->logger->send(provider: $provider->value, operation: 'key-check', query: $query, request: $request, category: null);
    }

    private function verdict(ApiKeyProvider $provider, Response $response): ApiKeyCheck
    {
        $graphQlCode = $response->json('errors.0.extensions.code');

        return match (true) {
            // A GraphQL error about the token is a refusal, whatever the HTTP status.
            in_array($graphQlCode, self::GRAPHQL_AUTH_ERRORS, true) => ApiKeyCheck::refused(self::reason($response)),
            $response->successful() && $graphQlCode === null => ApiKeyCheck::accepted(),
            // Rakuten's 404 is "no such book": the key itself was accepted.
            $provider === ApiKeyProvider::Rakuten && $response->notFound() => ApiKeyCheck::accepted(),
            in_array($response->status(), [400, 401, 403], true) => ApiKeyCheck::refused(self::reason($response)),
            // Down, quota used up, or an unexpected answer: the key may well be fine.
            default => ApiKeyCheck::unknown("{$provider->label()} answered ".self::reason($response).'.'),
        };
    }

    /** The provider's own words about the failure, as far as its answer has any. */
    private static function reason(Response $response): string
    {
        $message = $response->json('error.message')         // Google
            ?? $response->json('error_description')          // Rakuten
            ?? $response->json('errors.0.message')           // GraphQL (Hardcover)
            ?? $response->json('errors.errorMessage')        // Rakuten openapi host
            ?? $response->json('message');

        return is_string($message) && trim($message) !== ''
            ? Str::limit(trim($message), 200)
            : "HTTP {$response->status()}";
    }
}
