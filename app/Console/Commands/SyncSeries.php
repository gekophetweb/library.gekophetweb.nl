<?php

namespace App\Console\Commands;

use App\Jobs\SyncSeriesVolumes;
use App\Models\Series;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/** Weekly re-sync of linked series, to pick up newly published volumes (plan §5 step 18). */
#[Signature('series:sync {--days=7 : Re-sync series last fetched at least this many days ago}')]
#[Description('Queue a volume-list refresh for every linked series that is due')]
class SyncSeries extends Command
{
    public function handle(): int
    {
        $due = now()->subDays((int) $this->option('days'));
        $count = 0;

        Series::query()
            ->whereIn('external_source', Series::SYNCABLE_SOURCES)
            ->whereNotNull('external_id')
            ->where(fn (Builder $query) => $query->whereNull('synced_at')->orWhere('synced_at', '<=', $due))
            ->each(function (Series $series) use (&$count) {
                SyncSeriesVolumes::dispatch($series);
                $count++;
            });

        $this->info("Queued {$count} series.");

        return self::SUCCESS;
    }
}
