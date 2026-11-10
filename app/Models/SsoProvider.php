<?php

namespace App\Models;

use App\Enums\SsoDriver;
use Database\Factories\SsoProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An SSO provider an admin set up. The client secret is encrypted at rest. */
#[Fillable(['driver', 'name', 'client_id', 'client_secret', 'settings', 'enabled', 'allow_registration', 'sort'])]
#[Hidden(['client_secret'])]
class SsoProvider extends Model
{
    /** @use HasFactory<SsoProviderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'driver' => SsoDriver::class,
            'client_secret' => 'encrypted',
            'settings' => 'array',
            'enabled' => 'boolean',
            'allow_registration' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /** @return HasMany<UserIdentity, $this> */
    public function identities(): HasMany
    {
        return $this->hasMany(UserIdentity::class);
    }

    /** @param  Builder<self>  $query */
    #[Scope]
    protected function enabled(Builder $query): void
    {
        $query->where('enabled', true);
    }

    /** @param  Builder<self>  $query */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('id');
    }

    public function setting(string $key): ?string
    {
        $value = $this->settings[$key] ?? null;

        return $value === null || $value === '' ? null : (string) $value;
    }
}
