# Romanization fixtures

| File | Origin |
|---|---|
| `wikidata-latin-hakusensha.json` | **Hand-written** 2026-11-16 in the SPARQL JSON results format, for the `WikidataLatinName` query with `"白泉社"@ja`. |

Re-record it verbatim with the query `WikidataLatinName` builds:

```sh
curl -G https://query.wikidata.org/sparql -H 'Accept: application/sparql-results+json' \
  --data-urlencode 'query=SELECT ?latin WHERE { VALUES ?name { "白泉社"@ja } ?item rdfs:label ?name ; rdfs:label ?latin . FILTER(LANG(?latin) = "en") } LIMIT 1'
```
