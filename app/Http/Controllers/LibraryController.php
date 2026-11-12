<?php

namespace App\Http\Controllers;

use App\Models\Artist;
use App\Models\Category;
use App\Models\Language;
use App\Models\Series;
use App\Support\Library\CategoryCounts;
use App\Support\Library\CategoryShelf;
use App\Support\Library\SeriesShelf;
use Illuminate\Database\Eloquent\Builder;
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

    public function series(Request $request, Series $series): View
    {
        abort_unless($series->user_id === $request->user()->id, 404);

        return view('library.series', ['shelf' => SeriesShelf::build($series, $request->user()->objects()->getQuery())]);
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
