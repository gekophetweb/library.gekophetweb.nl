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
#[Fillable(['name', 'email'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_admin' => 'boolean',
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

    /** This user's own key for $provider, or null when none is set. */
    public function apiKey(ApiKeyProvider $provider): ?string
    {
        return $this->apiKeys()->where('provider', $provider->value)->first()?->key;
    }

    /** @return HasMany<UserIdentity, $this> */
    public function identities(): HasMany
    {
        return $this->hasMany(UserIdentity::class);
    }
}
