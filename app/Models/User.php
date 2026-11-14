<?php

namespace App\Models;

use App\Enums\ApiKeyProvider;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * There are no passwords: users log in through an SSO provider or an e-mail login code.
 * `is_admin` is deliberately not fillable — it is only ever set explicitly.
 */
#[Fillable(['name', 'email', 'suggestion_emails'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** Shown where a deleted account's suggestions and comments remain. */
    public const string DELETED_NAME = '[deleted user]';

    /**
     * Attributes of the stand-in for a deleted author (`withDefault` on author relations):
     * not an admin, and never mailed.
     *
     * @return array<string, mixed>
     */
    public static function deletedPlaceholder(): array
    {
        return ['name' => self::DELETED_NAME, 'email' => null, 'is_admin' => false, 'suggestion_emails' => false];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_admin' => 'boolean',
            'suggestion_emails' => 'boolean',
        ];
    }

    /** @return HasMany<CollectionObject, $this> */
    public function objects(): HasMany
    {
        return $this->hasMany(CollectionObject::class);
    }

    /** @return HasMany<Series, $this> */
    public function series(): HasMany
    {
        return $this->hasMany(Series::class);
    }

    /** @return HasMany<UserApiKey, $this> */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(UserApiKey::class);
    }

    /**
     * This user's own key for $provider, or null when none is set — or when the provider
     * also needs a secret (Rakuten's access key) and that is missing, so callers skip it.
     */
    public function apiKey(ApiKeyProvider $provider): ?string
    {
        $saved = $this->apiKeys()->where('provider', $provider->value)->first();

        return $saved !== null && self::isComplete($saved) ? $saved->key : null;
    }

    /** The second credential for $provider (Rakuten's access key), or null. */
    public function apiKeySecret(ApiKeyProvider $provider): ?string
    {
        return $provider->needsSecret()
            ? $this->apiKeys()->where('provider', $provider->value)->first()?->secret
            : null;
    }

    /**
     * Providers this user has no complete key for yet. Scanning needs all of them.
     *
     * @return list<ApiKeyProvider>
     */
    public function missingApiKeys(): array
    {
        $complete = $this->apiKeys()->get()
            ->filter(fn (UserApiKey $saved) => self::isComplete($saved))
            ->map(fn (UserApiKey $saved) => $saved->provider->value)
            ->all();

        return array_values(array_filter(
            ApiKeyProvider::cases(),
            fn (ApiKeyProvider $provider) => ! in_array($provider->value, $complete, true),
        ));
    }

    private static function isComplete(UserApiKey $saved): bool
    {
        return ! $saved->provider->needsSecret() || filled($saved->secret);
    }

    /** @return HasMany<Suggestion, $this> */
    public function suggestions(): HasMany
    {
        return $this->hasMany(Suggestion::class);
    }

    /** @return HasMany<UserIdentity, $this> */
    public function identities(): HasMany
    {
        return $this->hasMany(UserIdentity::class);
    }
}
