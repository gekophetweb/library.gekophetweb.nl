<?php

namespace App\Http\Controllers;

use App\Actions\Series\DetectSeries;
use App\Actions\Series\LinkSeries;
use App\Actions\UpdateCollectionObject;
use App\Enums\CategoryKind;
use App\Enums\OwnershipStatus;
use App\Http\Requests\UpdateCollectionObjectRequest;
use App\Http\Requests\UpdateObjectSeriesRequest;
use App\Models\Artist;
use App\Models\CollectionObject;
use App\Models\Language;
use App\Models\Platform;
use App\Models\Publisher;
use App\Models\Series;
use App\Support\Series\SeriesReference;
use App\Support\Series\VolumeNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CollectionObjectController extends Controller
{
    public function show(Request $request, CollectionObject $object): View
    {
        abort_unless($object->user_id === $request->user()->id, 404);

        $object->load(['category', 'subCategory', 'languages', 'genres', 'publisher', 'serie', 'bookDetail.artist', 'musicDetail.artist', 'gameDetail.platform']);
        $kind = $object->category->kind;

        return view('objects.show', [
            'object' => $object,
            'kind' => $kind,
            'options' => [
                'subCategories' => $object->category->children,
                'languages' => Language::query()->orderBy('name')->get(),
                'genres' => $object->category->genres()->orderBy('name')->get(),
                'publishers' => Publisher::query()->orderBy('name')->get(),
                'series' => Series::query()->ownedBy($request->user())->orderByDisplayName()->get(),
                'artists' => $kind === CategoryKind::Games ? collect() : Artist::query()->orderBy('name')->get(),
                'platforms' => $kind === CategoryKind::Games ? Platform::query()->orderBy('name')->get() : collect(),
            ],
        ]);
    }

    public function update(UpdateCollectionObjectRequest $request, CollectionObject $object, UpdateCollectionObject $update): RedirectResponse
    {
        $update->handle($object, $request->validated());

        return redirect()->route('objects.show', $object)->with('status', 'Saved.');
    }

    /** The one-click "Mark as owned" / "Move to wishlist" button. */
    public function updateOwnership(Request $request, CollectionObject $object): RedirectResponse
    {
        abort_unless($object->user_id === $request->user()->id, 404);

        $ownership = $request->validate(['ownership' => ['required', Rule::enum(OwnershipStatus::class)]])['ownership'];
        $object->update(['ownership' => $ownership]);

        return redirect()->route('objects.show', $object)->with('status', $object->isOwned() ? 'Marked as owned.' : 'Moved to your wishlist.');
    }

    /** Accept the series suggestion a batch scan or wishlist add left on the item. */
    public function acceptSeriesSuggestion(Request $request, CollectionObject $object, LinkSeries $link): RedirectResponse
    {
        abort_unless($object->user_id === $request->user()->id, 404);
        $suggestion = $object->series_suggestion ?? abort(404);

        $series = $link->toReference($object, SeriesReference::fromArray($suggestion['reference']), DetectSeries::suggestedVolume($suggestion));

        return redirect()->route('objects.show', $object)->with('status', "Added to the series {$series->name}.");
    }

    /**
     * Put a book in a series by hand, or move it to another one: a new name wins over the
     * picked series, and both empty takes the book out of its series.
     */
    public function updateSeries(UpdateObjectSeriesRequest $request, CollectionObject $object, LinkSeries $link): RedirectResponse
    {
        abort_unless($object->category->kind === CategoryKind::Books, 404);

        $name = trim((string) $request->validated('series_name'));
        $volume = VolumeNumber::parse($request->validated('series_volume'));

        $series = match (true) {
            $name !== '' => $link->byName($object, $name, $volume),
            filled($request->validated('serie_id')) => $link->toSeries($object, Series::query()->findOrFail($request->validated('serie_id')), $volume),
            default => null,
        };

        if ($series === null) {
            $link->unlink($object);

            return redirect()->route('objects.show', $object)->with('status', 'Taken out of its series.');
        }

        return redirect()->route('objects.show', $object)->with('status', "Added to the series {$series->display_name}.");
    }

    public function dismissSeriesSuggestion(Request $request, CollectionObject $object): RedirectResponse
    {
        abort_unless($object->user_id === $request->user()->id, 404);

        $object->update(['series_suggestion' => null]);

        return redirect()->route('objects.show', $object);
    }
}
