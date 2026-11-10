<?php

namespace App\Http\Controllers;

use App\Enums\CategoryKind;
use App\Http\Requests\UpdateCollectionObjectRequest;
use App\Models\Artist;
use App\Models\CollectionObject;
use App\Models\Language;
use App\Models\Platform;
use App\Models\Publisher;
use App\Models\Series;
use App\Support\Series\VolumeNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function update(UpdateCollectionObjectRequest $request, CollectionObject $object): RedirectResponse
    {
        abort_unless($object->user_id === $request->user()->id, 404);

        $data = $request->validated();

        DB::transaction(function () use ($object, $data) {
            $object->update([
                'name' => $data['name'],
                'name_romanized' => $data['name_romanized'],
                'name_english' => $data['name_english'] ?? null,
                'sub_category_id' => $data['sub_category_id'] ?? null,
                'publisher_id' => $data['publisher_id'] ?? null,
                'release_date' => $data['release_date'] ?? null,
                'serie_id' => $data['serie_id'] ?? null,
            ]);

            $object->languages()->sync($data['languages']);
            $object->genres()->sync($data['genres'] ?? []);

            // Category-specific details go to their own table only (§3.3).
            match ($object->category->kind) {
                CategoryKind::Books => $object->bookDetail()->updateOrCreate([], [
                    'artist_id' => $data['artist_id'] ?? null,
                    'volume' => ($volume = VolumeNumber::parse($data['volume'] ?? null)) ? (string) $volume : null,
                    'volume_sort' => $volume?->toFloat(),
                ]),
                CategoryKind::Music => $object->musicDetail()->updateOrCreate([], ['artist_id' => $data['artist_id'] ?? null]),
                CategoryKind::Games => $object->gameDetail()->updateOrCreate([], ['platform_id' => $data['platform_id'] ?? null]),
            };
        });

        return redirect()->route('objects.show', $object)->with('status', 'Saved.');
    }
}
