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

    /** Most lines one paste may hold; a box of books is far below this. */
    public const int MAX_BULK_LINES = 500;

    public function storeItem(Request $request, ScanBatch $batch): RedirectResponse
    {
        $this->authorizeBatch($request, $batch);
        abort_unless($batch->isDraft(), 409, 'This batch was already committed.');

        $barcode = $this->normalize((string) $request->validate(['barcode' => ['required', 'string', 'max:64']])['barcode']);

        if ($reason = $this->rejection($request, $batch, $barcode)) {
            throw ValidationException::withMessages(['barcode' => "{$barcode} {$reason}."]);
        }

        $batch->items()->create(['barcode' => $barcode]);

        return redirect()->route('scan.batches.show', $batch);
    }

    /**
     * A pasted list, one ISBN / barcode per line. Every line gets the same checks as a
     * single scan; lines that can't go on the list are reported and put back in the
     * textarea, without blocking the others.
     */
    public function storeItems(Request $request, ScanBatch $batch): RedirectResponse
    {
        $this->authorizeBatch($request, $batch);
        abort_unless($batch->isDraft(), 409, 'This batch was already committed.');

        $text = (string) $request->validate(['barcodes' => ['required', 'string', 'max:20000']])['barcodes'];
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $text)), fn (string $line) => $line !== ''));

        if ($lines === []) {
            throw ValidationException::withMessages(['barcodes' => 'Paste at least one ISBN or barcode, one per line.']);
        }

        if (count($lines) > self::MAX_BULK_LINES) {
            throw ValidationException::withMessages(['barcodes' => 'Paste at most '.self::MAX_BULK_LINES.' lines at a time.']);
        }

        $added = 0;
        $skipped = [];

        foreach ($lines as $line) {
            if (mb_strlen($line) > 64) {
                $skipped[] = ['line' => $line, 'reason' => 'is too long for a barcode'];

                continue;
            }

            $barcode = $this->normalize($line);

            if ($reason = $this->rejection($request, $batch, $barcode)) {
                $skipped[] = ['line' => $line, 'reason' => $reason];

                continue;
            }

            $batch->items()->create(['barcode' => $barcode]);
            $added++;
        }

        $redirect = redirect()->route('scan.batches.show', $batch)
            ->with('status', $added === 0 ? 'Nothing added.' : "Added {$added} ".str('item')->plural($added).' to the list.');

        if ($skipped !== []) {
            // Refill the textarea with only the lines that still need attention.
            $redirect->with('bulk_skipped', $skipped)
                ->withInput(['barcodes' => implode("\n", array_column($skipped, 'line'))]);
        }

        return $redirect;
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

    /** An ISBN in its canonical form, any other barcode without whitespace. */
    private function normalize(string $raw): string
    {
        $raw = trim($raw);

        return Isbn::tryFrom($raw)?->value ?? preg_replace('/\s+/', '', $raw);
    }

    /** Why $barcode can't go on this list (the end of a sentence), or null when it can. */
    private function rejection(Request $request, ScanBatch $batch, string $barcode): ?string
    {
        if ($batch->items()->where('barcode', $barcode)->exists()) {
            return 'is already on this list';
        }

        $existing = CollectionObject::query()->ownedBy($request->user())->where('barcode', $barcode)->first();

        // A wanted item may go on an Own list: committing it promotes it to owned.
        if ($existing?->isOwned()) {
            return 'is already in your collection';
        }

        if ($existing !== null && $batch->ownership === OwnershipStatus::Wishlist) {
            return 'is already on your wishlist';
        }

        return null;
    }

    /** Looks up the items whose catalogues didn't answer last time, again. */
    public function retry(Request $request, ScanBatch $batch): RedirectResponse
    {
        $this->authorizeBatch($request, $batch);
        abort_unless($batch->status === ScanBatch::DONE, 409, 'This batch is not finished yet.');

        $count = $batch->retryFailed();

        return redirect()->route('scan.batches.show', $batch)
            ->with('status', $count ? "Trying {$count} ".str('item')->plural($count).' again…' : 'Nothing to try again.');
    }

    private function authorizeBatch(Request $request, ScanBatch $batch): void
    {
        abort_unless($batch->user_id === $request->user()->id, 404);
    }
}
