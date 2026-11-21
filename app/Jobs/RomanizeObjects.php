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
     * Queues one job per item: each item's barcode is looked up again, which can take half a
     * minute, and the production worker stops a job after 120 seconds.
     *
     * @param  iterable<int>  $objectIds
     * @return int how many items were queued
     */
    public static function dispatchEach(iterable $objectIds): int
    {
        $count = 0;

        foreach ($objectIds as $id) {
            self::dispatch([(int) $id]);
            $count++;
        }

        return $count;
    }

    /**
     * Queues every item still needing a romanization.
     *
     * @return int how many items were queued
     */
    public static function dispatchAllNeeded(): int
    {
        return self::dispatchEach(CollectionObject::query()->needingRomanization()->orderBy('id')->pluck('id'));
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
