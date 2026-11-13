# Series lookup fixtures

| File | Origin |
|---|---|
| `ndl-*.xml` | **Recorded** 2026-11-09 from NDL Search OpenSearch (plan series-detection-from-isbn §2). |
| `openlibrary-isbn-9780593820247.json` | **Recorded** 2026-11-09 from Open Library. |
| `wikidata-*.json` | **Recorded** 2026-11-09 from the Wikidata SPARQL endpoint. |
| `googlebooks-*.json` | **Hand-written** 2026-11-13 from the documented `volumes`, `series/get` and `series/membership/get` response shapes (English *Berserk Deluxe*). |
| `hardcover-*.json` | **Hand-written** 2026-11-13 from Hardcover's GraphQL schema (`editions`, `book_series`, `series_by_pk`). |

The Google Books and Hardcover files are hand-written because neither API answers
without the user's key (Google Books returns `429` keyless). The IDs (`gbSeriesBerserkDx`,
series `4242`) are made up; the ISBNs are checksum-valid. **Re-record them with real
keys and replace these files verbatim** before relying on the field mapping in
production (plan series-google-hardcover §5):

```sh
curl "https://www.googleapis.com/books/v1/volumes?q=isbn:9781506711980&maxResults=5&key=$GOOGLE_BOOKS_KEY"
curl "https://www.googleapis.com/books/v1/series/get?series_id=<seriesId>&key=$GOOGLE_BOOKS_KEY"
curl "https://www.googleapis.com/books/v1/series/membership/get?series_id=<seriesId>&page_size=40&key=$GOOGLE_BOOKS_KEY"
curl -H "authorization: Bearer $HARDCOVER_TOKEN" -H 'content-type: application/json' \
  -d '{"query":"…the SeriesByIsbn query in HardcoverSeriesLookup…","variables":{"isbn":"9781506711980"}}' \
  https://api.hardcover.app/v1/graphql
```
