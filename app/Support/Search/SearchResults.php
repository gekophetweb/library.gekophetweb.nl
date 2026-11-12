<?php

namespace App\Support\Search;

use App\Enums\SearchScope;
use App\Models\Artist;
use App\Models\CollectionObject;
use App\Models\Series;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * One search, grouped: the user's matching items (paginated), then up to ten matching
 * series and up to ten matching creators. Every query is scoped to the user. Shared by
 * the web page and `GET /api/v1/search`.
 */
final readonly class SearchResults
{
    public const int SECTION_LIMIT = 10;

    public const array ITEM_RELATIONS = ['category', 'subCategory', 'serie', 'bookDetail.artist', 'musicDetail.artist', 'gameDetail.platform'];

    /**
     * @param  LengthAwarePaginator<int, CollectionObject>  $objects
     * @param  Collection<int, Series>  $series
     * @param  Collection<int, Artist>  $artists
     */
    public function __construct(
        public string $term,
        public SearchScope $scope,
        public LengthAwarePaginator $objects,
        public Collection $series,
        public Collection $artists,
    ) {}

    /** @param  array<int, string>  $relations */
    public static function for(User $user, string $term, SearchScope $scope, int $perPage = 25, array $relations = self::ITEM_RELATIONS): self
    {
        $objects = $user->objects()->getQuery()
            ->matchingSearch($term, $scope)
            ->with($relations)
            ->orderBy('objects.name')->orderBy('objects.id')
            ->paginate($perPage)
            ->withQueryString();

        $series = $scope->includesTitles()
            ? Series::query()->ownedBy($user)->whereNameLike($term)
                ->with('artist')
                ->withCount(['objects' => fn ($query) => $query->owned()])
                ->orderBy('name')->limit(self::SECTION_LIMIT)->get()
            : collect();

        $artists = $scope->includesCreators()
            ? Artist::query()->reachableBy($user)->whereNameLike($term)
                ->orderBy('name')->limit(self::SECTION_LIMIT)->get()
            : collect();

        return new self($term, $scope, $objects, $series, $artists);
    }
}
