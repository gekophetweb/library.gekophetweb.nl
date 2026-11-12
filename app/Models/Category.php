<?php

namespace App\Models;

use App\Enums\CategoryKind;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Books / Music / Games and their sub-categories (Manga, Vinyl, …), modelled as data. */
#[Fillable(['parent_id', 'kind', 'name', 'slug', 'sort'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['kind' => CategoryKind::class, 'sort' => 'integer'];
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort')->orderBy('id');
    }

    /** Genres are linked to top-level categories. @return HasMany<Genre, $this> */
    public function genres(): HasMany
    {
        return $this->hasMany(Genre::class);
    }

    public function isTopLevel(): bool
    {
        return $this->parent_id === null;
    }

    public function topLevel(): self
    {
        return $this->isTopLevel() ? $this : $this->parent;
    }
}
