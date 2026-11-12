<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use App\Http\Resources\V1\ArtistResource;
use App\Http\Resources\V1\CollectionObjectResource;
use App\Http\Resources\V1\SeriesResource;
use App\Support\Search\SearchResults;
use Illuminate\Http\JsonResponse;

/**
 * The web search page's grouped results in one request, so the phone app builds its
 * search screen without three calls. `q` blank → empty groups.
 */
class SearchController extends Controller
{
    public function __invoke(SearchRequest $request): JsonResponse
    {
        $term = $request->term();

        if ($term === null) {
            return response()->json(['data' => ['objects' => [], 'series' => [], 'artists' => []], 'meta' => ['total_objects' => 0]]);
        }

        $results = SearchResults::for($request->user(), $term, $request->scope(), relations: CatalogueController::OBJECT_RELATIONS);

        return response()->json([
            'data' => [
                'objects' => CollectionObjectResource::collection($results->objects->getCollection())->resolve($request),
                'series' => SeriesResource::collection($results->series)->resolve($request),
                'artists' => ArtistResource::collection($results->artists)->resolve($request),
            ],
            'meta' => [
                'q' => $term,
                'scope' => $results->scope->value,
                'total_objects' => $results->objects->total(),
                'current_page' => $results->objects->currentPage(),
                'last_page' => $results->objects->lastPage(),
            ],
        ]);
    }
}
