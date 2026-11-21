<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Language;
use App\Models\ScanBatch;
use App\Models\ScanBatchItem;
use App\Support\Isbn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Every user's scan batches, so abandoned ones can be cleaned up (they also keep a
 * language or sub-category "in use") and failed ones understood: the detail page shows
 * each scanned code with what happened to it, and can retry the failed lookups.
 */
class ScanBatchController extends Controller
{
    public const array STATUSES = [
        ScanBatch::DRAFT => 'Not committed',
        ScanBatch::QUEUED => 'Queued',
        ScanBatch::PROCESSING => 'Adding items',
        ScanBatch::DONE => 'Done',
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(self::STATUSES))],
            'language' => ['nullable', 'integer'],
            'category' => ['nullable', 'integer'],
            'mode' => ['nullable', Rule::in([ScanBatch::MODE_BATCH, ScanBatch::MODE_SINGLE])],
        ]);

        $batches = ScanBatch::query()
            ->with(['user', 'category', 'subCategory', 'language'])
            ->withCount([
                'items',
                'items as added_count' => fn ($query) => $query->whereIn('status', ['added', 'promoted']),
                'items as failed_count' => fn ($query) => $query->where('status', 'failed'),
            ])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['language'] ?? null, fn ($query, $id) => $query->where('language_id', $id))
            ->when($filters['category'] ?? null, fn ($query, $id) => $query->where(fn ($query) => $query->where('category_id', $id)->orWhere('sub_category_id', $id)))
            ->when($filters['mode'] ?? null, fn ($query, $mode) => $query->where('mode', $mode))
            ->latest()->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.scan-batches.index', [
            'batches' => $batches,
            'statuses' => self::STATUSES,
            'filters' => $filters,
            'language' => isset($filters['language']) ? Language::query()->find($filters['language']) : null,
            'category' => isset($filters['category']) ? Category::query()->find($filters['category']) : null,
        ]);
    }

    /** What was scanned and what happened to each code. */
    public function show(ScanBatch $batch): View
    {
        $batch->load(['user', 'category', 'subCategory', 'language']);
        $items = $batch->items()->with(['object:id,name,serie_id,category_id', 'object.category'])->withCount('apiCalls')->get();

        return view('admin.scan-batches.show', [
            'batch' => $batch,
            'items' => $items,
            'statuses' => self::STATUSES,
            'counts' => $items->countBy('status'),
        ]);
    }

    public function retry(ScanBatch $batch): RedirectResponse
    {
        abort_unless($batch->status === ScanBatch::DONE, 409, 'This batch is not finished yet.');

        $count = $batch->retryFailed();

        return redirect()->route('admin.scan-batches.show', $batch)
            ->with('status', $count ? "Looking up {$count} ".str('item')->plural($count).' again…' : 'Nothing to try again.');
    }

    /** Looks every code that was not added (failed or not found) up again, with the owner's keys. */
    public function redo(ScanBatch $batch): RedirectResponse
    {
        abort_unless($batch->status === ScanBatch::DONE, 409, 'This batch is not finished yet.');

        $count = $batch->redo();

        return redirect()->route('admin.scan-batches.show', $batch)
            ->with('status', $count ? "Redoing {$count} ".str('code')->plural($count).'…' : 'Every code in this batch was already added; nothing to redo.');
    }

    /** Looks the codes the admin ticked up again; codes already in the collection are skipped. */
    public function redoItems(Request $request, ScanBatch $batch): RedirectResponse
    {
        abort_unless($batch->status === ScanBatch::DONE, 409, 'This batch is not finished yet.');

        $ids = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['integer'],
        ], ['items.required' => 'Tick the codes to redo first.'])['items'];

        $count = $batch->redoItems(array_map('intval', $ids));

        return redirect()->route('admin.scan-batches.show', $batch)
            ->with('status', $count ? "Redoing {$count} ".str('code')->plural($count).'…' : 'Those codes are already in the collection; nothing to redo.');
    }

    /** Corrects a mis-scanned code that did not reach the collection, and looks it up again. */
    public function updateItemBarcode(Request $request, ScanBatch $batch, ScanBatchItem $item): RedirectResponse
    {
        abort_unless($batch->status === ScanBatch::DONE, 409, 'This batch is not finished yet.');
        abort_unless($item->object_id === null, 409, 'This code is already in the collection.');

        $raw = trim((string) $request->validateWithBag("barcode-{$item->id}", ['barcode' => ['required', 'string', 'max:64']])['barcode']);
        $barcode = Isbn::tryFrom($raw)?->value ?? (string) preg_replace('/\s+/', '', $raw);

        if ($barcode === $item->barcode) {
            throw ValidationException::withMessages(['barcode' => 'That is the code it already has.'])->errorBag("barcode-{$item->id}");
        }

        if ($batch->items()->where('barcode', $barcode)->exists()) {
            throw ValidationException::withMessages(['barcode' => "{$barcode} is already in this batch."])->errorBag("barcode-{$item->id}");
        }

        $old = $item->barcode;
        $batch->rescanItem($item, $barcode);

        return redirect()->route('admin.scan-batches.show', $batch)
            ->with('status', "Changed {$old} to {$barcode}; looking it up again…");
    }

    /** The raw answers of every external API call made for one scanned code. */
    public function showItem(ScanBatch $batch, ScanBatchItem $item): View
    {
        $batch->load('user');

        return view('admin.scan-batches.item', [
            'batch' => $batch,
            'item' => $item->load('object:id,name'),
            'calls' => $item->apiCalls()->get(),
        ]);
    }

    public function destroy(Request $request, ScanBatch $batch): RedirectResponse
    {
        if ($batch->isRunning()) {
            throw ValidationException::withMessages(['batch' => 'This batch is still adding items in the background. Remove it once it is done.']);
        }

        $batch->delete();

        // Back to the same filtered page; the scanned codes went with the batch.
        return redirect()->to(url()->previous(route('admin.scan-batches.index')))
            ->with('status', 'Scan batch removed. Items it already added stay in the collection.');
    }
}
