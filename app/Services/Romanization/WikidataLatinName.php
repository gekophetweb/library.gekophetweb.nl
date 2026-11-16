<?php

namespace App\Services\Romanization;

use App\Services\ApiCallLogger;
use App\Support\Series\Text;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;

/**
 * The Latin name of a publisher or person known only in kanji / hanja (`白泉社` →
 * `Hakusensha`, `三浦建太郎` → `Kentaro Miura`): the English label of the Wikidata item
 * whose Japanese / Korean label is exactly that name. One logged SPARQL call; null when
 * Wikidata has no such item or does not answer.
 */
class WikidataLatinName
{
    public function __construct(private readonly Http $http, private readonly ApiCallLogger $logger) {}

    public function find(string $name, string $language): ?string
    {
        // Catalogues write some person names with a space between family and given name.
        $variants = array_unique([$name, str_replace(' ', '', $name)]);
        $values = implode(' ', array_map(fn (string $variant) => '"'.addcslashes($variant, '"\\').'"@'.$language, $variants));

        $query = <<<SPARQL
            SELECT ?latin WHERE {
              VALUES ?name { {$values} }
              ?item rdfs:label ?name ; rdfs:label ?latin .
              FILTER(LANG(?latin) = "en")
            } LIMIT 1
            SPARQL;

        try {
            $response = $this->logger->send(
                provider: 'wikidata',
                operation: 'romanize',
                query: $name,
                request: fn () => $this->http
                    ->withUserAgent(config('series.user_agent'))
                    ->timeout(config('series.timeout'))
                    ->accept('application/sparql-results+json')
                    ->get(config('series.providers.wikidata.endpoint'), ['query' => $query]),
                language: $language,
                category: null,
            );
        } catch (ConnectionException) {
            return null;
        }

        $latin = $response->successful() ? $response->json('results.bindings.0.latin.value') : null;

        return is_string($latin) && ($latin = Text::clean($latin)) !== null && Text::isLatin($latin) ? $latin : null;
    }
}
