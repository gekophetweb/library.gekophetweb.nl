<?php

namespace App\Models;

use Database\Factories\ArtistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A book author or music artist, shared across users. */
#[Fillable(['name', 'name_romanized', 'name_english'])]
class Artist extends Model
{
    /** @use HasFactory<ArtistFactory> */
    use HasFactory;

    /** @return BelongsToMany<Language, $this> */
    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class);
    }
}
