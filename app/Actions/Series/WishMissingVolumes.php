<?php

namespace App\Actions\Series;

use App\Actions\Scan\AddScannedObject;
use App\Enums\OwnershipStatus;
use App\Models\CollectionObject;
use App\Models\Series;
use App\Models\SeriesVolume;
use App\Support\Library\SeriesShelf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Puts known-but-missing volumes of a series on the user's wishlist (plan
 * series-missing-to-wishlist). The new item copies where it goes — category,
 * sub-category, language — from the newest item already in the series, and takes the
 * volume's first ISBN as its barcode, so a later scan in Own mode promotes it.
 */
class WishMissingVolumes
{
    /**
     * @param  Collection<int, SeriesVolume>|null  $only  limit to these volumes; null = every missing one
     * @return Collection<int, CollectionObject> the items created (volumes already owned,
     *                                           wanted, or whose ISBN the user has are skipped)
     */
    public function handle(Series $series, ?Collection $only = null): Collection
    {
        $template = $this->template($series);

        if ($template === null) {
            return collect();
        }

        $user = $series->user;
        $missing = SeriesShelf::build($series, $user->objects()->getQuery())->volumes
            ->where('state', SeriesShelf::MISSING)
            ->pluck('volume')
            ->when($only !== null, fn (Collection $volumes) => $volumes->whereIn('id', $only->pluck('id')))
            ->values();

        $taken = CollectionObject::query()->ownedBy($user)
            ->whereIn('barcode', $missing->flatMap(fn (SeriesVolume $volume) => $volume->isbns ?? []))
            ->pluck('barcode')->flip();

        return DB::transaction(fn () => $missing
            ->reject(fn (SeriesVolume $volume) => collect($volume->isbns ?? [])->contains(fn (string $isbn) => $taken->has($isbn)))
            ->map(fn (SeriesVolume $volume) => $this->create($series, $volume, $template))
            ->values());
    }

    /** Whether there is an item to copy category and language from. */
    public function canWish(Series $series): bool
    {
        return $this->template($series) !== null;
    }

    private function template(Series $series): ?CollectionObject
    {
        return $series->objects()->with('languages')->latest('id')->first();
    }

    private function create(Series $series, SeriesVolume $volume, CollectionObject $template): CollectionObject
    {
        $label = trim($volume->volume_number.' '.$volume->edition);

        $object = new CollectionObject([
            'category_id' => $template->category_id,
            'sub_category_id' => $template->sub_category_id,
            'barcode' => ($volume->isbns ?? [])[0] ?? null,
            'ownership' => OwnershipStatus::Wishlist,
            'name' => $volume->title ?: "{$series->name} {$label}",
            'name_romanized' => ($series->name_romanized ?: $series->name).' '.$label,
            'name_english' => $series->name_english ? "{$series->name_english} {$label}" : null,
            'publisher_id' => $series->publisher_id,
            'release_date' => AddScannedObject::normalizeDate($volume->release_date),
            'serie_id' => $series->id,
        ]);
        $object->user()->associate($series->user_id)->save();
        $object->languages()->attach($template->languages->pluck('id'));
        $object->bookDetail()->create([
            'artist_id' => $series->artist_id,
            'volume' => $volume->volume_number,
            'volume_sort' => $volume->volume_sort,
        ]);

        return $object;
    }
}
