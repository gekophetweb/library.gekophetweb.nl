<?php

namespace App\Support\Library;

use App\Models\CollectionObject;
use App\Models\Series;
use App\Models\SeriesVolume;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * A series page: every known volume marked owned, wanted (on the wishlist) or missing,
 * plus items that match no known volume. An item matches a volume by one of its ISBNs,
 * else (regular edition only) by its volume number. Owned items are matched first, so a
 * volume that is both owned and wishlisted shows as owned.
 */
final readonly class SeriesShelf
{
    public const string OWNED = 'owned';

    public const string WANTED = 'wanted';

    public const string MISSING = 'missing';

    /**
     * @param  Collection<int, array{volume: SeriesVolume, state: string, object: ?CollectionObject}>  $volumes
     * @param  Collection<int, CollectionObject>  $unmatched
     */
    public function __construct(
        public Series $series,
        public Collection $volumes,
        public Collection $unmatched,
        public int $ownedCount,
        public int $itemCount,
        public int $wantedCount = 0,
    ) {}

    /** @param  Builder<CollectionObject>  $objects  already scoped to what the viewer may see */
    public static function build(Series $series, Builder $objects): self
    {
        $items = (clone $objects)->where('objects.serie_id', $series->id)
            ->with(['bookDetail.artist', 'subCategory'])
            ->get()
            ->sortBy(fn (CollectionObject $object) => [(float) ($object->bookDetail?->volume_sort ?? PHP_INT_MAX), $object->name])
            ->values();

        [$owned, $wanted] = $items->partition(fn (CollectionObject $object) => $object->isOwned());

        // Items whose barcode is a known ISBN belong to that volume and nowhere else.
        $knownIsbns = $series->volumes->flatMap(fn (SeriesVolume $volume) => $volume->isbns ?? [])->flip();
        $claimed = [];

        $match = function (SeriesVolume $volume, Collection $candidates) use ($knownIsbns, &$claimed): ?CollectionObject {
            $byBarcode = $candidates->filter(fn (CollectionObject $object) => $object->barcode !== null && ! isset($claimed[$object->id]))->keyBy('barcode');
            $object = collect($volume->isbns ?? [])->map(fn (string $isbn) => $byBarcode->get($isbn))->filter()->first();

            if ($object === null && ! $volume->isSpecialEdition()) {
                $object = $candidates->first(fn (CollectionObject $item) => ! isset($claimed[$item->id])
                    && ! $knownIsbns->has($item->barcode ?? '')
                    && $item->bookDetail?->volume_sort !== null
                    && (float) $item->bookDetail->volume_sort === (float) $volume->volume_sort);
            }

            if ($object !== null) {
                $claimed[$object->id] = true;
            }

            return $object;
        };

        $volumes = $series->volumes->map(fn (SeriesVolume $volume) => ($object = $match($volume, $owned))
            ? ['volume' => $volume, 'state' => self::OWNED, 'object' => $object]
            : ['volume' => $volume, 'state' => self::MISSING, 'object' => null]);

        // Second pass: still-missing volumes that are on the wishlist.
        $volumes = $volumes->map(fn (array $entry) => $entry['state'] === self::MISSING && ($object = $match($entry['volume'], $wanted))
            ? ['volume' => $entry['volume'], 'state' => self::WANTED, 'object' => $object]
            : $entry);

        return new self(
            series: $series,
            volumes: $volumes,
            unmatched: $items->reject(fn (CollectionObject $object) => isset($claimed[$object->id]))->values(),
            ownedCount: $volumes->where('state', self::OWNED)->count(),
            itemCount: $owned->count(),
            wantedCount: $wanted->count(),
        );
    }
}
