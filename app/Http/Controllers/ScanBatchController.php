<?php

namespace App\Http\Controllers;

use App\Enums\OwnershipStatus;
use App\Jobs\ProcessScanBatch;
use App\Models\CollectionObject;
use App\Models\ScanBatch;
use App\Models\ScanBatchItem;
use App\Support\Isbn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Batch scanning (guidelines §4.2): barcodes collect on a working list that is not part of
 * the collection yet; items can be removed; committing hands the list to a queued job.
 */
class ScanBatchController extends Controller
{
    public function show(Request $request, ScanBatch $batch): View
    {
        $this->authorizeBatch($request, $batch);

        return view('scan.batch', [
            'batch' => $batch->load(['category', 'subCategory', 'language']),
            'items' => $batch->items()->with('object')->get(),
        ]);
    }

    public function storeItem(Request $request, ScanBatch $batch): RedirectResponse
    {
        $this->authorizeBatch($request, $batch);
        abort_unless($batch->isDraft(), 409, 'This batch was already committed.');

        $raw = trim((string) $request->validate(['barcode' => ['required', 'string', 'max:64']])['barcode']);
        $barcode = Isbn::tryFrom($raw)?->value ?? preg_replace('/\s+/', '', $raw);

        if ($batch->items()->where('barcode', $barcode)->exists()) {
            throw ValidationException::withMessages(['barcode' => "{$barcode} is already on this list."]);
        }

        $existing = CollectionObject::query()->ownedBy($request->user())->where('barcode', $barcode)->first();

        // A wanted item may go on an Own list: committing it promotes it to owned.
        if ($existing?->isOwned()) {
            throw ValidationException::withMessages(['barcode' => "{$barcode} is already in your collection."]);
        }

        if ($existing !== null && $batch->ownership === OwnershipStatus::Wishlist) {
            throw ValidationException::withMessages(['barcode' => "{$barcode} is already on your wishlist."]);
        }

        $batch->items()->create(['barcode' => $barcode]);

        return redirect()->route('scan.batches.show', $batch);
    }

    public function destroyItem(Request $request, ScanBatch $batch, ScanBatchItem $item): RedirectResponse
    {
        $this->authorizeBatch($request, $batch);
        abort_unless($item->scan_batch_id === $batch->id, 404);
        abort_unless($batch->isDraft(), 409, 'This batch was already committed.');

        $item->delete();

        return redirect()->route('scan.batches.show', $batch);
    }

    public function commit(Request $request, ScanBatch $batch): RedirectResponse
    {
        $this->authorizeBatch($request, $batch);
        abort_unless($batch->isDraft(), 409, 'This batch was already committed.');

        if (! $batch->items()->exists()) {
            throw ValidationException::withMessages(['barcode' => 'Scan at least one item first.']);
        }

        $batch->update(['status' => ScanBatch::QUEUED, 'committed_at' => now()]);
        ProcessScanBatch::dispatch($batch);

        return redirect()->route('scan.batches.show', $batch);
    }

    private function authorizeBatch(Request $request, ScanBatch $batch): void
    {
        abort_unless($batch->user_id === $request->user()->id, 404);
    }
}
