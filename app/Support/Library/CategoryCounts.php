<?php

namespace App\Support\Library;

use App\Models\Category;
use App\Models\CollectionObject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The top-level categories with how many items an object query holds in each. Takes a
 * pre-scoped query so the owner's dashboard and a share link build on the same code.
 */
final class CategoryCounts
{
    /**
     * @param  Builder<CollectionObject>  $objects
     * @return Collection<int, Category> each with an `items_count` attribute
     */
    public static function for(Builder $objects): Collection
    {
        $counts = (clone $objects)
            ->reorder()
            ->selectRaw('category_id, count(*) as aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id');

        return Category::query()->whereNull('parent_id')->orderBy('sort')->orderBy('id')->get()
            ->each(fn (Category $category) => $category->setAttribute('items_count', (int) ($counts[$category->id] ?? 0)));
    }
}
