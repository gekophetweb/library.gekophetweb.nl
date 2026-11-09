<?php

namespace App\Services;

use App\Models\ApiCallLog;
use Closure;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Wraps an outgoing HTTP call and writes one api_call_logs row for it, whatever the
 * outcome. Every external catalogue / series call goes through here (guidelines §4.3).
 */
class ApiCallLogger
{
    public function __construct(private readonly Auth $auth) {}

    /**
     * @param  Closure(): Response  $request
     *
     * @throws Throwable when the request itself throws (connection error, timeout);
     *                   the failure is logged first.
     */
    public function send(
        string $provider,
        string $operation,
        string $query,
        Closure $request,
        ?string $language = null,
        ?string $category = 'books',
    ): Response {
        $started = hrtime(true);

        try {
            $response = $request();
        } catch (Throwable $e) {
            $this->write($provider, $operation, $query, $language, $category, $started, ApiCallLog::OUTCOME_ERROR, null, $e->getMessage());

            throw $e;
        }

        $outcome = match (true) {
            $response->successful() => ApiCallLog::OUTCOME_SUCCESS,
            $response->notFound() => ApiCallLog::OUTCOME_NOT_FOUND,
            default => ApiCallLog::OUTCOME_FAILED,
        };

        $this->write(
            $provider, $operation, $query, $language, $category, $started, $outcome, $response->status(),
            $outcome === ApiCallLog::OUTCOME_FAILED ? mb_substr($response->body(), 0, 1000) : null,
        );

        return $response;
    }

    private function write(
        string $provider,
        string $operation,
        string $query,
        ?string $language,
        ?string $category,
        int $started,
        string $outcome,
        ?int $status,
        ?string $error,
    ): void {
        ApiCallLog::query()->create([
            'user_id' => $this->auth->guard()->id(),
            'provider' => $provider,
            'operation' => $operation,
            'query' => $query,
            'category' => $category,
            'language' => $language,
            'outcome' => $outcome,
            'status_code' => $status,
            'duration_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
            'error' => $error,
        ]);
    }
}
