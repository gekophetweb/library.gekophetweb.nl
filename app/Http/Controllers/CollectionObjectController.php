<?php

namespace App\Http\Controllers;

use App\Actions\UpdateCollectionObject;
use App\Enums\CategoryKind;
use App\Enums\OwnershipStatus;
use App\Http\Requests\UpdateCollectionObjectRequest;
use App\Models\Artist;
use App\Models\CollectionObject;
use App\Models\Language;
use App\Models\Platform;
use App\Models\Publisher;
use App\Models\Series;
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
                'series' => Series::query()->ownedBy($request->user())->orderBy('name')->get(),
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
}
