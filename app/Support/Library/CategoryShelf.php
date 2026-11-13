<?php

namespace App\Support\Library;

use App\Enums\OwnershipStatus;
use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\Series;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * What a category page shows: its series (with owned and wanted counts), its owned
 * standalone items, and the wishlist for the category in a section of its own.
 */
final readonly class CategoryShelf
{
    /**
     * @param  Collection<int, Series>  $series  each with `items_count` (owned) and `wanted_count`
     * @param  Collection<int, CollectionObject>  $standalone  owned, not in a series
     * @param  Collection<int, CollectionObject>  $wishlist  every wanted item, series or not
     * @param  Collection<int, Category>  $subCategories
     */
    public function __construct(
        public Category $category,
        public ?Category $subCategory,
        public Collection $series,
        public Collection $standalone,
        public Collection $wishlist,
        public Collection $subCategories,
    ) {}

    /** @param  Builder<CollectionObject>  $objects  already scoped to what the viewer may see */
    public static function build(Category $category, Builder $objects, ?int $subCategoryId = null): self
    {
        $subCategory = $subCategoryId ? $category->children()->whereKey($subCategoryId)->first() : null;
        $with = ['subCategory', 'bookDetail.artist', 'musicDetail.artist', 'gameDetail.platform'];

        $inShelf = (clone $objects)
            ->where('objects.category_id', $category->id)
            ->when($subCategory, fn (Builder $query) => $query->where('objects.sub_category_id', $subCategory->id));

        $counts = (clone $inShelf)->whereNotNull('serie_id')
            ->selectRaw('serie_id, ownership, count(*) as aggregate')->groupBy('serie_id', 'ownership')
            ->toBase()
            ->get()
            ->groupBy('serie_id');

        $count = fn (int $seriesId, OwnershipStatus $status) => (int) ($counts->get($seriesId)
            ?->first(fn ($row) => $row->ownership === $status->value)
            ?->aggregate ?? 0);

        $series = Series::query()->whereKey($counts->keys())->with('artist')->orderBy('name')->get()
            ->each(fn (Series $series) => $series
                ->setAttribute('items_count', $count($series->id, OwnershipStatus::Owned))
                ->setAttribute('wanted_count', $count($series->id, OwnershipStatus::Wishlist)));

        $standalone = (clone $inShelf)->owned()->whereNull('serie_id')->with($with)->orderBy('name')->get();
        $wishlist = (clone $inShelf)->wishlisted()->with([...$with, 'serie'])->orderBy('name')->get();

        return new self($category, $subCategory, $series, $standalone, $wishlist, $category->children);
    }
}
