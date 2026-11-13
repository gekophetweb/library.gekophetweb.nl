<?php

namespace App\Services\Series;

use App\Contracts\KeyedSeriesLookup;
use App\Contracts\SeriesLookup;
use App\Models\User;
use App\Support\Isbn;
use App\Support\Series\Confidence;
use App\Support\Series\SeriesMatch;
use App\Support\Series\SeriesReference;
use App\Support\Series\SeriesResolution;
use App\Support\Series\SeriesVolumeData;
use App\Support\Series\Text;
use App\Support\Series\TitleVolumeParser;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;

/**
 * Picks the provider chain for the scan language (config/series.php), stops at the
 * first high-confidence match and keeps the rest as suggestions. Call sites depend on
 * this class only, so providers can be added in config. Keyed providers (Google Books,
 * Hardcover) get the user's own key, and are skipped when there is no user or no key.
 */
class SeriesLookupManager
{
    /** ISO 639-2 codes some catalogues send, mapped to the 639-1 keys used in config. */
    private const array LANGUAGE_ALIASES = ['jpn' => 'ja', 'eng' => 'en'];

    public function __construct(
        private readonly Container $container,
        private readonly Cache $cache,
        private readonly TitleVolumeParser $titleParser,
    ) {}

    /**
     * @param  string|null  $knownTitle  Title from the catalogue lookup, used for the
     *                                   title-parsing fallback when every provider misses.
     */
    public function resolve(Isbn $isbn, string $language, ?string $knownTitle = null, ?User $user = null): SeriesResolution
    {
        $language = $this->normalizeLanguage($language);
        $chain = $this->chainFor($language, $user);
        // Which keyed providers took part is part of the answer: a user who adds a key
        // later must not get the cached keyless result.
        $keyed = collect($chain)->filter(fn (SeriesLookup $p) => $p instanceof KeyedSeriesLookup)->map->name()->implode(',');

        /** @var list<SeriesMatch> $matches */
        $matches = $this->rememberUnlessEmpty(
            "series:resolve:{$language}:{$isbn->value}".($keyed !== '' ? ":{$keyed}" : ''),
            config('series.cache.resolve_ttl'),
            fn () => $this->runChain($chain, $isbn, $language),
        );

        if ($matches === [] && ($fallback = $this->fromTitle($knownTitle)) !== null) {
            $matches = [$fallback];
        }

        return new SeriesResolution($matches);
    }

    /** @return Collection<int, SeriesVolumeData> */
    public function volumes(SeriesReference $series, ?User $user = null): Collection
    {
        $provider = collect($this->allProviders($user))->first(fn (SeriesLookup $p) => $p->supports($series));

        if ($provider === null) {
            return collect();
        }

        return $this->rememberUnlessEmpty(
            'series:volumes:'.sha1($series->key()),
            config('series.cache.volumes_ttl'),
            fn () => $provider->volumes($series),
        );
    }

    /**
     * Like Cache::remember(), but an empty result is not stored: providers return empty
     * on failed calls too, and a timeout must not hide a series for days.
     *
     * @template T of array|Collection
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function rememberUnlessEmpty(string $key, int $ttl, callable $callback): array|Collection
    {
        $cached = $this->cache->get($key);

        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();

        if (count($value) > 0) {
            $this->cache->put($key, $value, $ttl);
        }

        return $value;
    }

    /** @return list<SeriesLookup> */
    public function chainFor(string $language, ?User $user = null): array
    {
        $chains = config('series.chains');

        return $this->instantiate($chains[$this->normalizeLanguage($language)] ?? $chains['default'] ?? [], $user);
    }

    /**
     * @param  list<SeriesLookup>  $chain
     * @return list<SeriesMatch>
     */
    private function runChain(array $chain, Isbn $isbn, string $language): array
    {
        $matches = [];

        foreach ($chain as $provider) {
            $match = $provider->resolve($isbn, $language);

            if ($match === null) {
                continue;
            }

            $matches[] = $match;

            if ($match->confidence === Confidence::High) {
                break;
            }
        }

        // Best first; usort is stable, so chain order breaks ties.
        usort($matches, fn (SeriesMatch $a, SeriesMatch $b) => $b->confidence->rank() <=> $a->confidence->rank());

        return $matches;
    }

    private function fromTitle(?string $title): ?SeriesMatch
    {
        $parsed = $this->titleParser->parse($title);

        if ($parsed === null) {
            return null;
        }

        return new SeriesMatch(
            series: new SeriesReference(
                source: 'title',
                externalId: Text::key($parsed->title),
                name: $parsed->title,
                nameRomanized: Text::isLatin($parsed->title) ? $parsed->title : null,
            ),
            volume: $parsed->volume,
            confidence: Confidence::Low,
            source: 'title',
        );
    }

    /** @return list<SeriesLookup> */
    private function allProviders(?User $user): array
    {
        return $this->instantiate(collect(config('series.chains'))->flatten()->unique()->values()->all(), $user);
    }

    /**
     * Builds the providers; a keyed one gets $user's key, or is left out without one.
     *
     * @param  list<class-string<SeriesLookup>>  $classes
     * @return list<SeriesLookup>
     */
    private function instantiate(array $classes, ?User $user): array
    {
        $providers = [];

        foreach ($classes as $class) {
            $provider = $this->container->make($class);

            if ($provider instanceof KeyedSeriesLookup) {
                $key = $user?->apiKey($provider->requiredKey());

                if ($key === null || $key === '') {
                    continue;
                }

                $provider = $provider->withKey($key);
            }

            $providers[] = $provider;
        }

        return $providers;
    }

    private function normalizeLanguage(string $language): string
    {
        $primary = strtolower(preg_split('/[-_]/', trim($language))[0] ?? '');

        return self::LANGUAGE_ALIASES[$primary] ?? $primary;
    }
}
