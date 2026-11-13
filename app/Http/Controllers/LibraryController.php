<?php

namespace App\Http\Controllers;

use App\Actions\Series\WishMissingVolumes;
use App\Jobs\SyncSeriesVolumes;
use App\Models\Artist;
use App\Models\Category;
use App\Models\Language;
use App\Models\Series;
use App\Models\SeriesVolume;
use App\Support\Library\CategoryCounts;
use App\Support\Library\CategoryShelf;
use App\Support\Library\SeriesShelf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The owner's library: dashboard → category → series. */
class LibraryController extends Controller
{
    public function dashboard(Request $request): View
    {
        return view('library.dashboard', [
            'categories' => CategoryCounts::for($request->user()->objects()->getQuery()),
            'scanCategories' => Category::query()->whereNull('parent_id')->with('children')->orderBy('sort')->orderBy('id')->get(),
            'languages' => Language::query()->orderBy('name')->get(),
        ]);
    }

    public function category(Request $request, Category $category): View
    {
        abort_unless($category->isTopLevel(), 404);

        return view('library.category', [
            'shelf' => CategoryShelf::build($category, $request->user()->objects()->getQuery(), $request->integer('sub') ?: null),
        ]);
    }

    public function series(Request $request, Series $series, WishMissingVolumes $wish): View
    {
        abort_unless($series->user_id === $request->user()->id, 404);

        return view('library.series', [
            'shelf' => SeriesShelf::build($series, $request->user()->objects()->getQuery()),
            'canWish' => $wish->canWish($series),
        ]);
    }

    /** Put one missing volume, or every missing one, on the wishlist. */
    public function wishMissing(Request $request, Series $series, WishMissingVolumes $wish, ?SeriesVolume $volume = null): RedirectResponse
    {
        abort_unless($series->user_id === $request->user()->id, 404);
        abort_unless($wish->canWish($series), 409, 'This series has no item to copy the category from.');

        $added = $wish->handle($series, $volume ? collect([$volume]) : null);

        return redirect()->route('library.series', $series)->with('status', match ($added->count()) {
            0 => 'Nothing to add: those volumes are already in your library.',
            1 => "Added {$added->first()->name} to your wishlist.",
            default => "Added {$added->count()} volumes to your wishlist.",
        });
    }

    /** "Fetch volumes": refresh the series' volume list from its source, on the queue. */
    public function syncSeries(Request $request, Series $series): RedirectResponse
    {
        abort_unless($series->user_id === $request->user()->id, 404);
        abort_unless($series->isSyncable(), 409, 'This series is not linked to a catalogue.');

        SyncSeriesVolumes::dispatch($series);

        return redirect()->route('library.series', $series)->with('status', 'Fetching the volume list. Reload in a moment to see it.');
    }

    /** An author's or artist's page: the user's own series and items by them. */
    public function artist(Request $request, Artist $artist): View
    {
        $user = $request->user();
        // Artists are shared rows; one the user has nothing by does not exist for them.
        abort_unless(Artist::query()->reachableBy($user)->whereKey($artist->id)->exists(), 404);

        $byArtist = fn (Builder $details) => $details->where('artist_id', $artist->id);

        return view('library.artist', [
            'artist' => $artist,
            'series' => Series::query()->ownedBy($user)->where('artist_id', $artist->id)
                ->withCount(['objects' => fn ($query) => $query->owned()])
                ->orderBy('name')->get(),
            'objects' => $user->objects()->getQuery()
                ->where(fn (Builder $query) => $query
                    ->whereHas('bookDetail', $byArtist)
                    ->orWhereHas('musicDetail', $byArtist)
                    ->orWhereHas('serie', $byArtist))
                ->with(['subCategory', 'bookDetail.artist', 'musicDetail.artist', 'gameDetail.platform'])
                ->orderBy('name')->get(),
        ]);
    }
}
