<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RomanizeObjects;
use App\Models\CollectionObject;
use App\Models\ScanBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admins redo the romanized names (title, publisher, author / artist) of scanned items:
 * ticked codes on a scan batch page, or one item from its page. Queued (RomanizeObjects),
 * as it looks every barcode up again; placeholders only, hand-typed names stay.
 */
class RomanizationController extends Controller
{
    public function batchItems(Request $request, ScanBatch $batch): RedirectResponse
    {
        $ids = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['integer'],
        ], ['items.required' => 'Tick the codes to redo first.'])['items'];

        $objectIds = $batch->items()->whereKey(array_map('intval', $ids))->whereNotNull('object_id')->pluck('object_id')->unique()->values()->all();

        if ($objectIds !== []) {
            RomanizeObjects::dispatch($objectIds);
        }

        $count = count($objectIds);

        return redirect()->route('admin.scan-batches.show', $batch)->with('status', $count
            ? "Romanizing {$count} ".str('item')->plural($count).' again…'
            : 'None of those codes reached the collection; nothing to romanize.');
    }

    /** Every Japanese or Korean item that still has a placeholder name, from the overview. */
    public function all(): RedirectResponse
    {
        $count = RomanizeObjects::dispatchAllNeeded();

        return redirect()->route('admin.home')->with('status', $count
            ? "Romanizing {$count} ".str('item')->plural($count).' again…'
            : 'No items need romanizing.');
    }

    public function object(CollectionObject $object): RedirectResponse
    {
        RomanizeObjects::dispatch([$object->id]);

        return redirect()->route('objects.show', $object)->with('status', 'Romanizing the title, publisher and author again…');
    }
}
