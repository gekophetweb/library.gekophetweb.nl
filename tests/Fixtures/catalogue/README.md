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
