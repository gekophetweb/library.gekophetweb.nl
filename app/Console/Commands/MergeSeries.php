<?php

namespace App\Console\Commands;

use App\Jobs\SyncSeriesVolumes;
use App\Models\CollectionObject;
use App\Models\LibraryShare;
use App\Models\Series;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Folds a duplicate series into another of the same owner: its items (collection and
 * wishlist) and share links move over, the duplicate and its volume list go, and the kept
 * series syncs its volume list again so it picks up the volumes the duplicate had.
 */
#[Signature('series:merge {from : ID of the series to remove} {into : ID of the series to keep}')]
#[Description('Merge a duplicate series into another series of the same owner')]
class MergeSeries extends Command
{
    public function handle(): int
    {
        $from = Series::query()->find($this->argument('from'));
        $into = Series::query()->find($this->argument('into'));

        if ($from === null || $into === null || $from->is($into)) {
            $this->error('Give two different existing series IDs.');

            return self::FAILURE;
        }

        if ($from->user_id !== $into->user_id) {
            $this->error('Both series must belong to the same user.');

            return self::FAILURE;
        }

        $items = CollectionObject::query()->where('serie_id', $from->id)->count();
        $noun = $items === 1 ? 'item' : 'items';

        if (! $this->confirm("Move {$items} {$noun} from series {$from->id} \"{$from->name}\" into series {$into->id} \"{$into->name}\" and delete series {$from->id}?")) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($from, $into) {
            CollectionObject::query()->where('serie_id', $from->id)->update(['serie_id' => $into->id]);

            LibraryShare::query()
                ->where('shareable_type', $from->getMorphClass())
                ->where('shareable_id', $from->id)
                ->update(['shareable_id' => $into->id]);

            $from->delete();
            $into->update(['synced_at' => null]);
        });

        if ($into->isSyncable()) {
            SyncSeriesVolumes::dispatch($into, fresh: true);
        }

        $this->info("Merged series {$from->id} into {$into->id}.");

        return self::SUCCESS;
    }
}
