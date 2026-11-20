<?php

namespace App\Jobs;

use App\Actions\Admin\RedoRomanization;
use App\Models\CollectionObject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Redoes the romanized names of the items an admin picked (RedoRomanization), on the queue. */
class RomanizeObjects implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** @param  list<int>  $objectIds */
    public function __construct(public array $objectIds) {}

    /**
     * Queues every item still needing a romanization, in jobs of 50.
     *
     * @return int how many items were queued
     */
    public static function dispatchAllNeeded(): int
    {
        $ids = CollectionObject::query()->needingRomanization()->orderBy('id')->pluck('id');

        $ids->chunk(50)->each(fn ($chunk) => self::dispatch($chunk->values()->all()));

        return $ids->count();
    }

    public function handle(RedoRomanization $redo): void
    {
        CollectionObject::query()->whereKey($this->objectIds)->each(function (CollectionObject $object) use ($redo) {
            try {
                $redo->handle($object);
            } catch (Throwable $e) {
                // One item failing does not stop the others.
                report($e);
            }
        });
    }
}
