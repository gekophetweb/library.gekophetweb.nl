<?php

namespace App\Http\Resources\V1;

use App\Models\Series;
use App\Support\Library\SeriesShelf;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A series; with `shelf` set (the detail endpoint) it also lists every known volume as
 * owned or missing.
 *
 * @mixin Series
 */
class SeriesResource extends JsonResource
{
    public ?SeriesShelf $shelf = null;

    public function withShelf(SeriesShelf $shelf): static
    {
        $this->shelf = $shelf;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_romanized' => $this->name_romanized,
            'name_english' => $this->name_english,
            'creator' => $this->artist ? ['id' => $this->artist->id, 'name' => $this->artist->name] : null,
            'external_source' => $this->external_source,
            'external_id' => $this->external_id,
            'items_count' => $this->whenCounted('objects'),
            $this->mergeWhen($this->shelf !== null, fn () => [
                'owned_count' => $this->shelf->ownedCount,
                'items_count' => $this->shelf->itemCount,
                'volumes' => $this->shelf->volumes->map(fn (array $entry) => [
                    'volume_number' => $entry['volume']->volume_number,
                    'edition' => $entry['volume']->edition !== '' ? $entry['volume']->edition : null,
                    'isbns' => $entry['volume']->isbns ?? [],
                    'state' => $entry['state'],
                    'object_id' => $entry['object']?->id,
                ])->values(),
            ]),
        ];
    }
}
