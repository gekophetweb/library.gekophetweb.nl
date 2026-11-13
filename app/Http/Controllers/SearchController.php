<?php

namespace App\Http\Controllers;

use App\Enums\SearchScope;
use App\Http\Requests\SearchRequest;
use App\Models\Category;
use App\Models\Language;
use App\Services\Catalogue\CatalogueSearchManager;
use App\Support\Search\SearchResults;
use Illuminate\View\View;

/**
 * The header search box. Titles, creators or both search the user's own library; "Not
 * owned yet" searches the external catalogues so a book can go on the wishlist.
 */
class SearchController extends Controller
{
    public function index(SearchRequest $request, CatalogueSearchManager $catalogue): View
    {
        $term = $request->term();
        $scope = $request->scope();

        if ($scope->isCatalogue()) {
            $outcome = $term === null ? null : $catalogue->search($term, $request->catalogueContext());

            return view('search.catalogue', [
                'term' => $term,
                'scope' => $scope,
                'category' => $request->category(),
                'subCategory' => $request->subCategory(),
                'language' => $request->language(),
                'categories' => Category::query()->whereNull('parent_id')->with('children')->orderBy('sort')->orderBy('id')->get(),
                'languages' => Language::query()->orderBy('name')->get(),
                'outcome' => $outcome,
                'existing' => $outcome ? CatalogueSearchManager::existing($request->user(), $outcome->results) : collect(),
            ]);
        }

        return view('search.index', [
            'term' => $term,
            'scope' => $scope,
            'scopes' => SearchScope::cases(),
            // A blank term shows the empty page instead of listing everything.
            'results' => $term === null ? null : SearchResults::for($request->user(), $term, $scope),
        ]);
    }
}
