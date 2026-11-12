<?php

namespace App\Http\Controllers;

use App\Enums\SearchScope;
use App\Http\Requests\SearchRequest;
use App\Support\Search\SearchResults;
use Illuminate\View\View;

/** The header search box: titles, creators or both, across the user's own library only. */
class SearchController extends Controller
{
    public function index(SearchRequest $request): View
    {
        $term = $request->term();

        return view('search.index', [
            'term' => $term,
            'scope' => $request->scope(),
            'scopes' => SearchScope::cases(),
            // A blank term shows the empty page instead of listing everything.
            'results' => $term === null ? null : SearchResults::for($request->user(), $term, $request->scope()),
        ]);
    }
}
