<?php

namespace App\Http\Controllers;

use App\Actions\Scan\AddScannedObject;
use App\Http\Requests\StartScanRequest;
use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\Language;
use App\Models\ScanBatch;
use App\Services\Catalogue\CatalogueLookupManager;
use App\Support\Catalogue\CatalogueContext;
use App\Support\Catalogue\CatalogueResult;
use App\Support\Isbn;
use App\Support\Scan\ScanSetup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Single-item scanning (guidelines §4.1): look the barcode up, say whether it is already
 * in the collection, otherwise offer to add it. The scanner set-up lives in the session.
 */
class ScanController extends Controller
{
    public function start(StartScanRequest $request): RedirectResponse
    {
        $setup = $request->setup();

        if ($request->input('mode') === 'batch') {
            $batch = new ScanBatch([
                'category_id' => $setup->category->id,
                'sub_category_id' => $setup->subCategory?->id,
                'language_id' => $setup->language->id,
            ]);
            $batch->user()->associate($request->user())->save();

            return redirect()->route('scan.batches.show', $batch);
        }

        $request->session()->put('scan.setup', [
            'category_id' => $setup->category->id,
            'sub_category_id' => $setup->subCategory?->id,
            'language_id' => $setup->language->id,
        ]);
        $request->session()->forget('scan.pending');

        return redirect()->route('scan.single');
    }

    public function single(Request $request): View|RedirectResponse
    {
        $setup = $this->setup($request);

        if ($setup === null) {
            return redirect()->route('home');
        }

        $pending = $request->session()->get('scan.pending');

        return view('scan.single', [
            'setup' => $setup,
            'pending' => $pending,
            'result' => isset($pending['result']) ? CatalogueResult::fromArray($pending['result']) : null,
            'owned' => isset($pending['owned_id']) ? CollectionObject::query()->ownedBy($request->user())->find($pending['owned_id']) : null,
        ]);
    }

    public function lookup(Request $request, CatalogueLookupManager $catalogue): RedirectResponse
    {
        $setup = $this->setup($request) ?? abort(409, 'Choose what to scan first.');
        $barcode = trim((string) $request->validate(['barcode' => ['required', 'string', 'max:64']])['barcode']);
        $barcode = Isbn::tryFrom($barcode)?->value ?? preg_replace('/\s+/', '', $barcode);

        $owned = CollectionObject::query()->ownedBy($request->user())->where('barcode', $barcode)->first();

        if ($owned !== null) {
            $request->session()->put('scan.pending', ['barcode' => $barcode, 'owned_id' => $owned->id]);

            return redirect()->route('scan.single');
        }

        $outcome = $catalogue->lookup($barcode, new CatalogueContext($request->user(), $setup->category, $setup->language->code));

        $request->session()->put('scan.pending', [
            'barcode' => $outcome->result?->barcode ?? $barcode,
            'result' => $outcome->result?->toArray(),
            'missing_keys' => array_map(fn ($key) => $key->label(), $outcome->missingKeys),
            'no_provider' => $outcome->noProvider,
            'invalid' => $outcome->invalidBarcode,
        ]);

        return redirect()->route('scan.single');
    }

    public function add(Request $request, AddScannedObject $add): RedirectResponse
    {
        $setup = $this->setup($request) ?? abort(409, 'Choose what to scan first.');
        $pending = $request->session()->get('scan.pending') ?? abort(409, 'Scan something first.');

        if (isset($pending['result'])) {
            $result = CatalogueResult::fromArray($pending['result']);
        } else {
            // Not found by any provider: the user types the required details (§7).
            $typed = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'name_romanized' => ['required', 'string', 'max:255'],
            ]);
            $result = new CatalogueResult('manual', $pending['barcode'], $typed['name'], $typed['name_romanized']);
        }

        $object = $add->handle($request->user(), $setup, $result);
        $request->session()->forget('scan.pending');

        return redirect()->route('objects.show', $object)->with('status', 'Added to your collection. Scan the next one from the dashboard.');
    }

    private function setup(Request $request): ?ScanSetup
    {
        $stored = $request->session()->get('scan.setup');

        if ($stored === null) {
            return null;
        }

        return new ScanSetup(
            Category::query()->findOrFail($stored['category_id']),
            $stored['sub_category_id'] ? Category::query()->find($stored['sub_category_id']) : null,
            Language::query()->findOrFail($stored['language_id']),
        );
    }
}
