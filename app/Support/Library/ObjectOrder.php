<?php

namespace App\Support\Library;

use App\Models\CollectionObject;
use Illuminate\Support\Collection;

/**
 * The order of a mixed list of items: books from one series stay together, in volume
 * order, at their series' place by name; other items by name. Names compare naturally,
 * so "Vol. 2" comes before "Vol. 10".
 */
final class ObjectOrder
{
    /**
     * @template TCollection of Collection<int, CollectionObject>
     *
     * @param  TCollection  $objects  with `serie` and `bookDetail` loaded
     * @return TCollection
     */
    public static function bySeriesVolume(Collection $objects): Collection
    {
        return $objects->sort(function (CollectionObject $a, CollectionObject $b): int {
            return strnatcasecmp(self::place($a), self::place($b))
                ?: ($a->serie_id <=> $b->serie_id)
                ?: (self::volume($a) <=> self::volume($b))
                ?: strnatcasecmp((string) $a->name, (string) $b->name)
                ?: ($a->id <=> $b->id);
        })->values();
    }

    /** Where the item sorts: its series' name, or its own. */
    private static function place(CollectionObject $object): string
    {
        return (string) ($object->serie?->display_name ?? $object->name);
    }

    /** Its volume number; items without one go after the numbered ones. */
    private static function volume(CollectionObject $object): float
    {
        return $object->bookDetail?->volume_sort !== null ? (float) $object->bookDetail->volume_sort : INF;
    }
}
