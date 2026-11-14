<?php

namespace App\Models;

use Database\Factories\LanguageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name'])]
class Language extends Model
{
    /** @use HasFactory<LanguageFactory> */
    use HasFactory;

    /** @return BelongsToMany<CollectionObject, $this> */
    public function objects(): BelongsToMany
    {
        return $this->belongsToMany(CollectionObject::class, 'object_language', 'language_id', 'object_id');
    }

    /** @return BelongsToMany<Artist, $this> */
    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class);
    }

    /** @return HasMany<ScanBatch, $this> */
    public function scanBatches(): HasMany
    {
        return $this->hasMany(ScanBatch::class);
    }
}
