<?php

namespace App\Actions\Scan;

use App\Models\CollectionObject;
use App\Models\ScanBatch;
use App\Models\ScanBatchItem;
use App\Models\User;
use App\Support\Catalogue\CatalogueOutcome;
use App\Support\Scan\ScanSetup;

/**
 * Records single scans as codes in an automatic `single` scan batch, one per user and
 * scan setup, so admins can see what each lookup returned and redo it like a batch code.
 * Statuses and messages match ProcessScanBatch, except a found code waits as `not_added`
 * until the user adds it.
 */
class RecordSingleScan
{
    public function batchFor(User $user, ScanSetup $setup): ScanBatch
    {
        $batch = ScanBatch::query()
            ->where('user_id', $user->id)
            ->where('mode', ScanBatch::MODE_SINGLE)
            ->where('category_id', $setup->category->id)
            ->where('sub_category_id', $setup->subCategory?->id)
            ->where('language_id', $setup->language->id)
            ->where('ownership', $setup->ownership->value)
            ->first();

        if ($batch !== null) {
            return $batch;
        }

        $batch = new ScanBatch([
            'category_id' => $setup->category->id,
            'sub_category_id' => $setup->subCategory?->id,
            'language_id' => $setup->language->id,
            'ownership' => $setup->ownership,
            'mode' => ScanBatch::MODE_SINGLE,
            // Finished from the start, so an admin can redo its codes right away.
            'status' => ScanBatch::DONE,
            'committed_at' => now(),
            'finished_at' => now(),
        ]);
        $batch->forceFill(['user_id' => $user->id])->save();

        return $batch;
    }

    /**
     * The code's row in the batch, created or reset for a new lookup. It is `looking_up`
     * while the user's request runs, not `pending`, so a batch job started by an admin's
     * redo never adds it behind the user's back. createOrFirst: a code scanned twice at
     * once (a double tap) lands on one row instead of a duplicate-key error.
     */
    public function start(User $user, ScanSetup $setup, string $barcode): ScanBatchItem
    {
        $item = $this->batchFor($user, $setup)->items()->createOrFirst(['barcode' => $barcode], ['status' => ScanBatchItem::LOOKING_UP]);
        $item->update(['status' => ScanBatchItem::LOOKING_UP, 'message' => null, 'object_id' => null]);

        return $item;
    }

    public function outcome(ScanBatchItem $item, CatalogueOutcome $outcome): void
    {
        $item->update(match (true) {
            $outcome->invalidBarcode => ['status' => 'invalid', 'message' => 'Not a valid barcode for this category.'],
            $outcome->failed() => ['status' => 'failed', 'message' => "No answer from {$outcome->unavailableNames()}. Try again later."],
            $outcome->result === null => ['status' => 'not_found', 'message' => $outcome->missingKeys
                ? 'Not found. Adding your '.collect($outcome->missingKeys)->map->label()->join(', ').' key may help.'
                : null],
            default => ['status' => 'not_added'],
        });
    }

    public function failed(ScanBatchItem $item): void
    {
        $item->update(['status' => 'failed', 'message' => 'Something went wrong; try scanning it again.']);
    }

    /** Already in the collection, or already wanted: no lookup was made. */
    public function existing(ScanBatchItem $item, CollectionObject $existing): void
    {
        $item->update(['status' => $existing->isOwned() ? 'already_owned' : 'already_wanted', 'object_id' => $existing->id]);
    }

    /** The user added (or promoted) the scanned item. A scan from before recording began has no row. */
    public function added(?int $itemId, CollectionObject $object, string $status = 'added'): void
    {
        if ($itemId === null) {
            return;
        }

        ScanBatchItem::query()
            ->whereKey($itemId)
            ->whereHas('batch', fn ($batch) => $batch->where('user_id', $object->user_id))
            ->update(['status' => $status, 'object_id' => $object->id, 'message' => null]);
    }
}
