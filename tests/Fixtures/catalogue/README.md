# Catalogue lookup fixtures

| File | Origin |
|---|---|
| `googlebooks-429.json` | **Recorded** 2026-11-10: Google Books without a key answers `429` (daily quota for keyless use). Project number redacted. |
| `googlebooks-isbn-9780593820247.json` | **Hand-written** from the documented `volumes?q=isbn:` response shape (Dungeon Crawler Carl, book 1). |
| `rakuten-isbn-9784592135746.json` | **Hand-written** from the documented Books Book Search API (version 20170404, `formatVersion=2`) response shape (ベルセルク 1). |

The hand-written files exist because neither API could be called without a key when these
tests were written. **Re-record them with real keys** (and replace these files verbatim)
before relying on the field mapping in production:

```sh
curl "https://www.googleapis.com/books/v1/volumes?q=isbn:9780593820247&key=$GOOGLE_BOOKS_KEY"
curl "https://app.rakuten.co.jp/services/api/BooksBook/Search/20170404?format=json&formatVersion=2&isbn=9784592135746&applicationId=$RAKUTEN_APP_ID"
```

## Catalogue search (plan search-catalogue-not-owned)

| File | Origin |
|---|---|
| `ndl-search-berserk.xml` | **Recorded** 2026-11-12: `GET https://ndlsearch.ndl.go.jp/api/opensearch?title=ベルセルク&cnt=5` (two non-book records, three printed books). |
| `openlibrary-search-kafka.json` | **Recorded** 2026-11-12: `GET https://openlibrary.org/search.json?q=kafka+on+the+shore&fields=key,title,author_name,publisher,first_publish_year,isbn,cover_i,language&limit=5`. |

The Rakuten and Google Books search answers in `tests/Feature/Catalogue/CatalogueSearchTest.php`
are built inline from the documented response shapes (same caveat as above).
