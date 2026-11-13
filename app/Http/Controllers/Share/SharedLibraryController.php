<?php

namespace App\Http\Controllers\Share;

use App\Enums\CategoryKind;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveLibraryShare;
use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\LibraryShare;
use App\Models\Series;
use App\Support\Library\CategoryCounts;
use App\Support\Library\CategoryShelf;
use App\Support\Library\SeriesShelf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The read-only pages behind a share link. GET only, no session identity. Every page runs
 * through `LibraryShare::exposes()` and builds on `objectsQuery()`, with the same support
 * classes and views as the owner's pages.
 */
class SharedLibraryController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $share = $this->share($request);

        if ($category = $share->scopedCategory()) {
            return redirect()->route('share.category', $category);
        }

        if ($series = $share->scopedSeries()) {
            return redirect()->route('share.series', $series);
        }

        return view('share.index', [
            'share' => $share,
            'categories' => CategoryCounts::for($share->objectsQuery()),
        ]);
    }

    public function category(Request $request, Category $category): View
    {
        $share = $this->share($request);
        abort_unless($share->exposes($category), 404);

        return view('library.category', [
            'shelf' => CategoryShelf::build($category, $share->objectsQuery(), $request->integer('sub') ?: null),
            'share' => $share,
        ]);
    }

    public function series(Request $request, Series $series): View
    {
        $share = $this->share($request);
        abort_unless($share->exposes($series), 404);

        return view('library.series', [
            'shelf' => SeriesShelf::build($series, $share->objectsQuery()),
            'share' => $share,
        ]);
    }

    public function object(Request $request, CollectionObject $object): View
    {
        $share = $this->share($request);
        abort_unless($share->exposes($object), 404);

        $object->load(['category', 'subCategory', 'languages', 'genres', 'publisher', 'serie', 'bookDetail.artist', 'musicDetail.artist', 'gameDetail.platform']);

        return view('objects.show', [
            'object' => $object,
            'kind' => $object->category->kind ?? CategoryKind::Books,
            'options' => null,
            'share' => $share,
        ]);
    }

    private function share(Request $request): LibraryShare
    {
        return $request->attributes->get(ResolveLibraryShare::ATTRIBUTE);
    }
}
