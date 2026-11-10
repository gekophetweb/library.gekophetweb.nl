<?php

namespace App\Support\Library;

use App\Models\CollectionObject;
use App\Models\Series;
use App\Models\SeriesVolume;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * A series page: every known volume marked owned or missing, plus items that match no
 * known volume. An item owns a volume by one of its ISBNs, else (regular edition only)
 * by its volume number.
 */
final readonly class SeriesShelf
{
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
    ) {}

    /** @param  Builder<CollectionObject>  $objects  already scoped to what the viewer may see */
    public static function build(Series $series, Builder $objects): self
    {
        $items = (clone $objects)->where('objects.serie_id', $series->id)
            ->with(['bookDetail.artist', 'subCategory'])
            ->get()
            ->sortBy(fn (CollectionObject $object) => [(float) ($object->bookDetail?->volume_sort ?? PHP_INT_MAX), $object->name])
            ->values();

        $byBarcode = $items->filter(fn (CollectionObject $object) => $object->barcode !== null)->keyBy('barcode');
        // Items whose barcode is a known ISBN belong to that volume and nowhere else.
        $knownIsbns = $series->volumes->flatMap(fn (SeriesVolume $volume) => $volume->isbns ?? [])->flip();
        $claimed = [];

        $volumes = $series->volumes->map(function (SeriesVolume $volume) use ($items, $byBarcode, $knownIsbns, &$claimed) {
            $object = collect($volume->isbns ?? [])->map(fn (string $isbn) => $byBarcode->get($isbn))->filter()->first();

            if ($object === null && ! $volume->isSpecialEdition()) {
                $object = $items->first(fn (CollectionObject $item) => ! isset($claimed[$item->id])
                    && ! $knownIsbns->has($item->barcode ?? '')
                    && $item->bookDetail?->volume_sort !== null
                    && (float) $item->bookDetail->volume_sort === (float) $volume->volume_sort);
            }

            if ($object !== null) {
                $claimed[$object->id] = true;
            }

            return ['volume' => $volume, 'state' => $object ? 'owned' : 'missing', 'object' => $object];
        });

        return new self(
            series: $series,
            volumes: $volumes,
            unmatched: $items->reject(fn (CollectionObject $object) => isset($claimed[$object->id]))->values(),
            ownedCount: $volumes->where('state', 'owned')->count(),
            itemCount: $items->count(),
        );
    }
}
