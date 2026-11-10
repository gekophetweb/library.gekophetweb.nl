<?php

namespace App\Models;

use Database\Factories\UserIdentityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A user's account at one SSO provider, identified by the provider's stable subject. */
#[Fillable(['user_id', 'sso_provider_id', 'subject', 'email'])]
class UserIdentity extends Model
{
    /** @use HasFactory<UserIdentityFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<SsoProvider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(SsoProvider::class, 'sso_provider_id');
    }
}
