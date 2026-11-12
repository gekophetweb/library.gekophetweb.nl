<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Scan\AddScannedObject;
use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use App\Http\Requests\StoreWishlistItemRequest;
use App\Http\Resources\V1\ArtistResource;
use App\Http\Resources\V1\CollectionObjectResource;
use App\Http\Resources\V1\SeriesResource;
use App\Services\Catalogue\CatalogueSearchManager;
use App\Support\Catalogue\CatalogueResult;
use App\Support\Search\SearchResults;
use Illuminate\Http\JsonResponse;

/**
 * The web search page's results in one request, so the phone app builds its search screen
 * without several calls. `q` blank → empty groups. `scope=catalogue` searches the external
 * catalogues instead and labels each result with what the user already has.
 */
class SearchController extends Controller
{
    public function __invoke(SearchRequest $request, CatalogueSearchManager $catalogue): JsonResponse
    {
        $term = $request->term();

        if ($request->scope()->isCatalogue()) {
            return $this->catalogue($request, $catalogue, $term);
        }

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

    /** `POST /api/v1/wishlist`: one catalogue result onto the wishlist. 201 when new, 200 when already there. */
    public function storeWishlist(StoreWishlistItemRequest $request, AddScannedObject $add): JsonResponse
    {
        $object = $add->handle($request->user(), $request->setup(), $request->result());

        return (new CollectionObjectResource($object->load(CatalogueController::OBJECT_RELATIONS)))
            ->response()
            ->setStatusCode($object->wasRecentlyCreated ? 201 : 200);
    }

    private function catalogue(SearchRequest $request, CatalogueSearchManager $catalogue, ?string $term): JsonResponse
    {
        $context = $request->catalogueContext();
        $outcome = $term === null ? null : $catalogue->search($term, $context);
        $existing = $outcome ? CatalogueSearchManager::existing($request->user(), $outcome->results) : collect();

        return response()->json([
            'data' => [
                'catalogue' => collect($outcome?->results ?? [])->map(fn (CatalogueResult $result) => [
                    'source' => $result->source,
                    'isbn' => $result->barcode,
                    'name' => $result->name,
                    'name_romanized' => $result->nameRomanized,
                    'name_english' => $result->nameEnglish,
                    'creator' => $result->creator,
                    'publisher' => $result->publisher,
                    'release_date' => $result->releaseDate,
                    'cover_url' => $result->coverUrl,
                    // null when the user has neither the book nor it on the wishlist.
                    'ownership' => $existing->get($result->barcode)?->ownership->value,
                    'object_id' => $existing->get($result->barcode)?->id,
                ])->values(),
            ],
            'meta' => [
                'q' => $term,
                'scope' => 'catalogue',
                'category_id' => $context->category->id,
                'language' => $context->language,
                'missing_keys' => array_map(fn ($key) => $key->value, $outcome->missingKeys ?? []),
                'no_provider' => $outcome->noProvider ?? false,
            ],
        ]);
    }
}
