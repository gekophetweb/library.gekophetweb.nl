<?php

namespace App\Models;

use App\Enums\ApiKeyProvider;
use Database\Factories\UserApiKeyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A user's own key for an external provider, encrypted at rest (§5.1). */
#[Fillable(['provider', 'key'])]
#[Hidden(['key'])]
class UserApiKey extends Model
{
    /** @use HasFactory<UserApiKeyFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['provider' => ApiKeyProvider::class, 'key' => 'encrypted'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
