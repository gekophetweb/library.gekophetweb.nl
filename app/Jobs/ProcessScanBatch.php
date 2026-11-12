<?php

namespace App\Jobs;

use App\Actions\Scan\AddScannedObject;
use App\Models\CollectionObject;
use App\Models\ScanBatch;
use App\Models\ScanBatchItem;
use App\Services\Catalogue\CatalogueLookupManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Commits a batch scan (guidelines §4.2): looks every pending item up and adds the ones
 * found. Runs on the queue; re-running only touches items still pending.
 */
class ProcessScanBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public ScanBatch $batch) {}

    public function handle(CatalogueLookupManager $catalogue, AddScannedObject $add): void
    {
        $this->batch->update(['status' => ScanBatch::PROCESSING]);
        $user = $this->batch->user;
        $setup = $this->batch->setup();
        $context = $this->batch->context();

        $this->batch->items()->where('status', 'pending')->each(function (ScanBatchItem $item) use ($catalogue, $add, $user, $setup, $context) {
            $owned = CollectionObject::query()->ownedBy($user)->where('barcode', $item->barcode)->first();

            if ($owned !== null) {
                $item->update(['status' => 'already_owned', 'object_id' => $owned->id]);

                return;
            }

            try {
                $outcome = $catalogue->lookup($item->barcode, $context);

                if ($outcome->invalidBarcode) {
                    $item->update(['status' => 'invalid', 'message' => 'Not a valid barcode for this category.']);
                } elseif ($outcome->result === null) {
                    $item->update(['status' => 'not_found', 'message' => $outcome->missingKeys
                        ? 'Not found. Adding your '.collect($outcome->missingKeys)->map->label()->join(', ').' key may help.'
                        : null]);
                } else {
                    $object = $add->handle($user, $setup, $outcome->result);
                    $item->update(['status' => 'added', 'object_id' => $object->id]);
                }
            } catch (Throwable $e) {
                report($e);
                $item->update(['status' => 'failed', 'message' => 'Something went wrong; try scanning it again.']);
            }
        });

        $this->batch->update(['status' => ScanBatch::DONE, 'finished_at' => now()]);
    }
}
