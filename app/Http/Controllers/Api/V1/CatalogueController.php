<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\UpdateCollectionObject;
use App\Enums\OwnershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCollectionObjectRequest;
use App\Http\Resources\V1\CategoryResource;
use App\Http\Resources\V1\CollectionObjectResource;
use App\Http\Resources\V1\SeriesResource;
use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\Series;
use App\Support\Library\SeriesShelf;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** The phone app's view of the catalogue (guidelines §6). Everything is scoped to the token's user. */
class CatalogueController extends Controller
{
    private const array OBJECT_RELATIONS = ['category', 'subCategory', 'languages', 'genres', 'serie', 'publisher', 'bookDetail.artist', 'musicDetail.artist', 'gameDetail.platform'];

    public function user(Request $request): array
    {
        $user = $request->user();

        return ['data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]];
    }

    public function categories(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::query()->whereNull('parent_id')->with('children')->orderBy('sort')->orderBy('id')->get()
        );
    }

    public function objects(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'category_id' => ['nullable', 'integer'],
            'series_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            // Absent means both, so older phone-app builds see no change.
            'ownership' => ['nullable', Rule::enum(OwnershipStatus::class)],
        ]);

        return CollectionObjectResource::collection(
            CollectionObject::query()->ownedBy($request->user())
                ->when($filters['ownership'] ?? null, fn ($query, $ownership) => $query->where('ownership', $ownership))
                ->when($filters['category_id'] ?? null, fn ($query, $id) => $query->where('category_id', $id))
                ->when($filters['series_id'] ?? null, fn ($query, $id) => $query->where('serie_id', $id))
                ->with(self::OBJECT_RELATIONS)
                ->orderBy('name')->orderBy('id')
                ->paginate($filters['per_page'] ?? 25)
                ->withQueryString()
        );
    }

    public function object(Request $request, CollectionObject $object): CollectionObjectResource
    {
        abort_unless($object->user_id === $request->user()->id, 404);

        return new CollectionObjectResource($object->load(self::OBJECT_RELATIONS));
    }

    public function updateObject(UpdateCollectionObjectRequest $request, CollectionObject $object, UpdateCollectionObject $update): CollectionObjectResource
    {
        return new CollectionObjectResource($update->handle($object, $request->validated())->load(self::OBJECT_RELATIONS));
    }

    public function series(Request $request): AnonymousResourceCollection
    {
        return SeriesResource::collection(
            Series::query()->ownedBy($request->user())->with('artist')->withCount(['objects' => fn ($query) => $query->owned(), 'objects as wanted_count' => fn ($query) => $query->wishlisted()])->orderBy('name')->paginate(50)
        );
    }

    public function showSeries(Request $request, Series $series): SeriesResource
    {
        abort_unless($series->user_id === $request->user()->id, 404);

        return (new SeriesResource($series->load('artist')))
            ->withShelf(SeriesShelf::build($series, $request->user()->objects()->getQuery()));
    }
}
