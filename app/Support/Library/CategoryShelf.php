<?php

namespace App\Support\Library;

use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\Series;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** What a category page shows: its series (with item counts) and its standalone items. */
final readonly class CategoryShelf
{
    /**
     * @param  Collection<int, Series>  $series  each with `items_count`
     * @param  Collection<int, CollectionObject>  $standalone
     * @param  Collection<int, Category>  $subCategories
     */
    public function __construct(
        public Category $category,
        public ?Category $subCategory,
        public Collection $series,
        public Collection $standalone,
        public Collection $subCategories,
    ) {}

    /** @param  Builder<CollectionObject>  $objects  already scoped to what the viewer may see */
    public static function build(Category $category, Builder $objects, ?int $subCategoryId = null): self
    {
        $subCategory = $subCategoryId ? $category->children()->whereKey($subCategoryId)->first() : null;

        $inShelf = (clone $objects)
            ->where('objects.category_id', $category->id)
            ->when($subCategory, fn (Builder $query) => $query->where('objects.sub_category_id', $subCategory->id));

        $counts = (clone $inShelf)->whereNotNull('serie_id')
            ->selectRaw('serie_id, count(*) as aggregate')->groupBy('serie_id')
            ->pluck('aggregate', 'serie_id');

        $series = Series::query()->whereKey($counts->keys())->with('artist')->orderBy('name')->get()
            ->each(fn (Series $series) => $series->setAttribute('items_count', (int) $counts[$series->id]));

        $standalone = (clone $inShelf)->whereNull('serie_id')
            ->with(['subCategory', 'bookDetail.artist', 'musicDetail.artist', 'gameDetail.platform'])
            ->orderBy('name')->get();

        return new self($category, $subCategory, $series, $standalone, $category->children);
    }
}
