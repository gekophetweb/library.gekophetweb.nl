<?php

namespace App\Models;

use Database\Factories\GenreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A genre belongs to one top-level category; a Books genre is not valid for Games (§3.4). */
#[Fillable(['category_id', 'name'])]
class Genre extends Model
{
    /** @use HasFactory<GenreFactory> */
    use HasFactory;

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsToMany<CollectionObject, $this> */
    public function objects(): BelongsToMany
    {
        return $this->belongsToMany(CollectionObject::class, 'genre_object', 'genre_id', 'object_id');
    }

    public function fitsCategory(Category $category): bool
    {
        return $this->category_id === $category->topLevel()->id;
    }
}
