<?php

namespace App\Actions\Series;

use App\Jobs\SyncSeriesVolumes;
use App\Models\Artist;
use App\Models\CollectionObject;
use App\Models\Publisher;
use App\Models\Series;
use App\Support\Series\SeriesReference;
use App\Support\Series\Text;
use App\Support\Series\VolumeNumber;
use Illuminate\Support\Facades\DB;

/**
 * Puts an item in one of its owner's series, creating the series when needed. An existing
 * series is matched by external ID first, then by name, so re-scans and hand-made series
 * never duplicate; another user's series is never reused. A newly linked syncable series
 * gets its volume list fetched on the queue (plan series-detection-from-isbn §5 step 15).
 */
class LinkSeries
{
    public function toReference(CollectionObject $object, SeriesReference $reference, ?VolumeNumber $volume): Series
    {
        return $this->apply($object, $this->findOrCreate($object, $reference), $volume);
    }

    /** A series the user typed: matched by name among their own, else created without a source. */
    public function byName(CollectionObject $object, string $name, ?VolumeNumber $volume): Series
    {
        $series = $this->findByName($object, $name) ?? $this->create($object, new SeriesReference('manual', '', $name));

        return $this->apply($object, $series, $volume);
    }

    private function apply(CollectionObject $object, Series $series, ?VolumeNumber $volume): Series
    {
        DB::transaction(function () use ($object, $series, $volume) {
            $object->update(['serie_id' => $series->id, 'series_suggestion' => null]);

            if ($volume !== null) {
                $object->bookDetail()->updateOrCreate([], ['volume' => (string) $volume, 'volume_sort' => $volume->toFloat()]);
            }
        });

        if ($series->isSyncable() && $series->synced_at === null) {
            SyncSeriesVolumes::dispatch($series)->afterCommit();
        }

        return $series;
    }

    private function findOrCreate(CollectionObject $object, SeriesReference $reference): Series
    {
        $series = Series::query()->ownedBy($object->user)
            ->where('external_source', $reference->source)
            ->where('external_id', $reference->externalId)
            ->first();

        if ($series !== null) {
            return $series;
        }

        $series = $this->findByName($object, $reference->name);

        if ($series === null) {
            return $this->create($object, $reference);
        }

        // A hand-made series of the same name: link it to the source so it can sync.
        if (! filled($series->external_source) && in_array($reference->source, Series::SYNCABLE_SOURCES, true)) {
            $series->update(['external_source' => $reference->source, 'external_id' => $reference->externalId]);
        }

        return $series;
    }

    private function findByName(CollectionObject $object, string $name): ?Series
    {
        $key = Text::key($name);

        return Series::query()->ownedBy($object->user)->get()
            ->first(fn (Series $series) => in_array($key, [Text::key($series->name), Text::key($series->name_romanized), Text::key($series->name_english)], true));
    }

    private function create(CollectionObject $object, SeriesReference $reference): Series
    {
        $manual = $reference->source === 'manual';
        $series = new Series([
            'name' => $reference->name,
            // Wikidata's English / romaji label first; else the name itself when it is in Latin script.
            'name_romanized' => $reference->nameRomanized ?? (Text::isLatin($reference->name) ? $reference->name : null),
            'name_english' => $reference->nameEnglish,
            'artist_id' => $reference->creator ? Artist::query()->firstOrCreate(['name' => $reference->creator], ['name_romanized' => $reference->creator])->id : null,
            'publisher_id' => $reference->publisher ? Publisher::query()->firstOrCreate(['name' => $reference->publisher], ['name_romanized' => $reference->publisher])->id : null,
            'external_source' => $manual ? null : $reference->source,
            'external_id' => $manual ? null : $reference->externalId,
        ]);
        $series->user()->associate($object->user)->save();

        return $series;
    }
}
