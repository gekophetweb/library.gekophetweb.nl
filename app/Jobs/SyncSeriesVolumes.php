<?php

namespace App\Jobs;

use App\Models\Series;
use App\Models\SeriesVolume;
use App\Services\Series\SeriesLookupManager;
use App\Support\Isbn;
use App\Support\Series\SeriesVolumeData;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fetches a series' volume list from its source and upserts `series_volumes` by volume
 * number + edition, merging ISBNs (plan series-detection-from-isbn §5 phase 5). Never
 * deletes a volume: one the source stops listing may be one the user owns. An empty
 * answer (a failed call looks the same) changes nothing and leaves `synced_at` alone.
 */
class SyncSeriesVolumes implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 600;

    public function __construct(public Series $series) {}

    public function uniqueId(): string
    {
        return (string) $this->series->id;
    }

    public function handle(SeriesLookupManager $lookup): void
    {
        $reference = $this->series->reference();

        if ($reference === null || ! $this->series->isSyncable()) {
            return;
        }

        $volumes = $lookup->volumes($reference, $this->series->user);

        if ($volumes->isEmpty()) {
            return;
        }

        $volumes->each(function (SeriesVolumeData $data) {
            /** @var SeriesVolume $volume */
            $volume = $this->series->volumes()->firstOrNew([
                'volume_sort' => $data->volume->toFloat(),
                'edition' => $data->edition ?? '',
            ]);

            $isbns = collect($volume->isbns ?? [])
                ->merge(array_map(fn (Isbn $isbn) => $isbn->value, $data->isbns))
                ->unique()->values()->all();

            $volume->fill([
                'volume_number' => (string) $data->volume,
                'title' => $data->title ?? $volume->title,
                'isbns' => $isbns ?: null,
                'release_date' => $data->releaseDate ?? $volume->release_date,
            ])->save();
        });

        $this->series->forceFill(['synced_at' => now()])->save();
    }
}
