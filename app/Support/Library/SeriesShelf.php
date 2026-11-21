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
 * volume that is both owned and wishlisted shows as owned. An edition you lack is
 * OTHER_EDITION (shown, but neither missing nor counted) when you own or want another
 * edition of that number, or when it is a special edition (a 新装版) and the regular
 * edition is listed: a volume number is missing once, not once per edition.
 */
final readonly class SeriesShelf
{
    public const string OWNED = 'owned';

    public const string WANTED = 'wanted';

    public const string MISSING = 'missing';

    public const string OTHER_EDITION = 'other_edition';

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
        public int $volumeCount = 0,
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

        // A missing edition is not missing when the user owns or wants another edition of
        // that number, or (for a special edition) when the regular edition is listed too:
        // then only the regular one is.
        $number = fn (array $entry) => (string) (float) $entry['volume']->volume_sort;
        $covered = $volumes->where('state', '!==', self::MISSING)->mapWithKeys(fn (array $entry) => [$number($entry) => true]);
        $regular = $volumes->reject(fn (array $entry) => $entry['volume']->isSpecialEdition())->mapWithKeys(fn (array $entry) => [$number($entry) => true]);
        $volumes = $volumes->map(fn (array $entry) => $entry['state'] === self::MISSING
            && ($covered->has($number($entry)) || ($entry['volume']->isSpecialEdition() && $regular->has($number($entry))))
            ? ['volume' => $entry['volume'], 'state' => self::OTHER_EDITION, 'object' => null]
            : $entry);

        return new self(
            series: $series,
            volumes: $volumes,
            unmatched: $items->reject(fn (CollectionObject $object) => isset($claimed[$object->id]))->values(),
            ownedCount: $volumes->where('state', self::OWNED)->count(),
            itemCount: $owned->count(),
            wantedCount: $wanted->count(),
            volumeCount: $volumes->where('state', '!==', self::OTHER_EDITION)->count(),
        );
    }
}
