<?php

namespace App\Services\Series;

use App\Contracts\SeriesLookup;
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
 * this class only, so providers can be added in config.
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
    public function resolve(Isbn $isbn, string $language, ?string $knownTitle = null): SeriesResolution
    {
        $language = $this->normalizeLanguage($language);

        /** @var list<SeriesMatch> $matches */
        $matches = $this->rememberUnlessEmpty(
            "series:resolve:{$language}:{$isbn->value}",
            config('series.cache.resolve_ttl'),
            fn () => $this->runChain($isbn, $language),
        );

        if ($matches === [] && ($fallback = $this->fromTitle($knownTitle)) !== null) {
            $matches = [$fallback];
        }

        return new SeriesResolution($matches);
    }

    /** @return Collection<int, SeriesVolumeData> */
    public function volumes(SeriesReference $series): Collection
    {
        $provider = collect($this->allProviders())->first(fn (SeriesLookup $p) => $p->supports($series));

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
    public function chainFor(string $language): array
    {
        $chains = config('series.chains');
        $classes = $chains[$this->normalizeLanguage($language)] ?? $chains['default'] ?? [];

        return array_map(fn (string $class) => $this->container->make($class), $classes);
    }

    /** @return list<SeriesMatch> */
    private function runChain(Isbn $isbn, string $language): array
    {
        $matches = [];

        foreach ($this->chainFor($language) as $provider) {
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
    private function allProviders(): array
    {
        $classes = collect(config('series.chains'))->flatten()->unique()->values()->all();

        return array_map(fn (string $class) => $this->container->make($class), $classes);
    }

    private function normalizeLanguage(string $language): string
    {
        $primary = strtolower(preg_split('/[-_]/', trim($language))[0] ?? '');

        return self::LANGUAGE_ALIASES[$primary] ?? $primary;
    }
}
