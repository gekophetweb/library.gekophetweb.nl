<?php

namespace App\Http\Controllers;

use App\Actions\Scan\AddScannedObject;
use App\Actions\Series\DetectSeries;
use App\Actions\Series\LinkSeries;
use App\Enums\CategoryKind;
use App\Enums\OwnershipStatus;
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
use App\Support\Series\SeriesReference;
use App\Support\Series\Text;
use App\Support\Series\VolumeNumber;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Single-item scanning (guidelines §4.1): look the barcode up, say whether it is already
 * in the collection, otherwise offer to add it (owned or to the wishlist). A wanted item
 * scanned in Own mode is offered as "mark as owned". The scanner set-up lives in the session.
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
                'ownership' => $setup->ownership,
            ]);
            $batch->user()->associate($request->user())->save();

            return redirect()->route('scan.batches.show', $batch);
        }

        $request->session()->put('scan.setup', [
            'category_id' => $setup->category->id,
            'sub_category_id' => $setup->subCategory?->id,
            'language_id' => $setup->language->id,
            'ownership' => $setup->ownership->value,
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
            'existing' => isset($pending['existing_id']) ? CollectionObject::query()->ownedBy($request->user())->find($pending['existing_id']) : null,
        ]);
    }

    public function lookup(Request $request, CatalogueLookupManager $catalogue, DetectSeries $series): RedirectResponse
    {
        $setup = $this->setup($request) ?? abort(409, 'Choose what to scan first.');
        $barcode = trim((string) $request->validate(['barcode' => ['required', 'string', 'max:64']])['barcode']);
        $barcode = Isbn::tryFrom($barcode)?->value ?? preg_replace('/\s+/', '', $barcode);

        $existing = CollectionObject::query()->ownedBy($request->user())->where('barcode', $barcode)->first();

        if ($existing !== null) {
            // Already owned, or already wanted in Wishlist mode: nothing to add. A wanted
            // item in Own mode can be promoted from the page.
            $request->session()->put('scan.pending', [
                'barcode' => $barcode,
                'existing_id' => $existing->id,
                'can_promote' => ! $existing->isOwned() && $setup->ownership === OwnershipStatus::Owned,
            ]);

            return redirect()->route('scan.single');
        }

        $outcome = $catalogue->lookup($barcode, new CatalogueContext($request->user(), $setup->category, $setup->language->code));

        // Books: which series and volume is this? Shown pre-filled and editable (plan
        // series-detection-from-isbn §5 step 13), whatever the confidence.
        $match = ! $outcome->invalidBarcode && $setup->category->topLevel()->kind === CategoryKind::Books
            ? $series->resolve($outcome->result?->barcode ?? $barcode, $setup->language->code, $outcome->result?->name)->best()
            : null;

        $request->session()->put('scan.pending', [
            'barcode' => $outcome->result?->barcode ?? $barcode,
            'result' => $outcome->result?->toArray(),
            'missing_keys' => array_map(fn ($key) => $key->label(), $outcome->missingKeys),
            'no_provider' => $outcome->noProvider,
            'invalid' => $outcome->invalidBarcode,
            'series' => $match ? DetectSeries::suggestion($match) : null,
        ]);

        return redirect()->route('scan.single');
    }

    public function add(Request $request, AddScannedObject $add, LinkSeries $link): RedirectResponse
    {
        $setup = $this->setup($request) ?? abort(409, 'Choose what to scan first.');
        $pending = $request->session()->get('scan.pending') ?? abort(409, 'Scan something first.');

        if (isset($pending['existing_id'])) {
            abort_unless($pending['can_promote'] ?? false, 409, 'This is already in your collection.');
            $object = AddScannedObject::promoteIfAcquired(
                CollectionObject::query()->ownedBy($request->user())->findOrFail($pending['existing_id']),
                $setup->ownership,
            );
            $request->session()->forget('scan.pending');

            return redirect()->route('objects.show', $object)->with('status', 'Moved from your wishlist to your collection.');
        }

        $seriesInput = $request->validate([
            'series_name' => ['nullable', 'string', 'max:255'],
            'series_volume' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail) {
                if (VolumeNumber::parse((string) $value) === null) {
                    $fail('The volume must be a number, such as 3 or 3.5.');
                }
            }],
        ]);

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

        $this->linkSeries($object, $pending['series'] ?? null, $seriesInput, $link);

        $where = $object->isOwned() ? 'your collection' : 'your wishlist';

        return redirect()->route('objects.show', $object)->with('status', "Added to {$where}. Scan the next one from the dashboard.");
    }

    /**
     * The series fields of the add form: left as suggested, the detected series is linked
     * (with its source, so it can sync); renamed, a series of that name; emptied, none.
     *
     * @param  array<string, mixed>|null  $suggestion
     * @param  array{series_name?: ?string, series_volume?: ?string}  $input
     */
    private function linkSeries(CollectionObject $object, ?array $suggestion, array $input, LinkSeries $link): void
    {
        $name = trim((string) ($input['series_name'] ?? ''));

        if ($name === '' || ! $object->wasRecentlyCreated) {
            return;
        }

        $volume = VolumeNumber::parse($input['series_volume'] ?? null);

        if ($suggestion !== null && Text::key($name) === Text::key($suggestion['reference']['name'] ?? null)) {
            $link->toReference($object, SeriesReference::fromArray($suggestion['reference']), $volume);
        } else {
            $link->byName($object, $name, $volume);
        }
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
            OwnershipStatus::tryFrom($stored['ownership'] ?? '') ?? OwnershipStatus::Owned,
        );
    }
}
