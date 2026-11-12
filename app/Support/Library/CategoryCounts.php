<?php

namespace App\Support\Library;

use App\Enums\OwnershipStatus;
use App\Models\Category;
use App\Models\CollectionObject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The top-level categories with how many items an object query holds in each: owned items
 * (the collection size) and, separately, wanted ones. Takes a pre-scoped query so the
 * owner's dashboard and a share link build on the same code.
 */
final class CategoryCounts
{
    /**
     * @param  Builder<CollectionObject>  $objects
     * @return Collection<int, Category> each with `items_count` (owned) and `wanted_count`
     */
    public static function for(Builder $objects): Collection
    {
        $counts = (clone $objects)
            ->reorder()
            ->selectRaw('category_id, ownership, count(*) as aggregate')
            ->groupBy('category_id', 'ownership')
            ->toBase()
            ->get()
            ->groupBy('category_id');

        $count = fn (int $categoryId, OwnershipStatus $status) => (int) ($counts->get($categoryId)
            ?->first(fn ($row) => $row->ownership === $status->value)
            ?->aggregate ?? 0);

        return Category::query()->whereNull('parent_id')->orderBy('sort')->orderBy('id')->get()
            ->each(fn (Category $category) => $category
                ->setAttribute('items_count', $count($category->id, OwnershipStatus::Owned))
                ->setAttribute('wanted_count', $count($category->id, OwnershipStatus::Wishlist)));
    }
}
