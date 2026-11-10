<?php

namespace App\Models;

use Database\Factories\PublisherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'name_romanized', 'name_english', 'logo'])]
class Publisher extends Model
{
    /** @use HasFactory<PublisherFactory> */
    use HasFactory;

    /** @return HasMany<CollectionObject, $this> */
    public function objects(): HasMany
    {
        return $this->hasMany(CollectionObject::class);
    }
}
