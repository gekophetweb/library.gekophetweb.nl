<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Language;
use App\Models\ScanBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Every user's scan batches, so abandoned ones can be cleaned up (they also keep a
 * language or sub-category "in use"). Like the user list, this shows that a batch exists
 * and how many codes it has, never the scanned barcodes themselves.
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
        ]);

        $batches = ScanBatch::query()
            ->with(['user', 'category', 'subCategory', 'language'])
            ->withCount('items')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['language'] ?? null, fn ($query, $id) => $query->where('language_id', $id))
            ->when($filters['category'] ?? null, fn ($query, $id) => $query->where(fn ($query) => $query->where('category_id', $id)->orWhere('sub_category_id', $id)))
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
