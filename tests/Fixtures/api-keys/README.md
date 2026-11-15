# API key check fixtures

Answers to the one test call made before a user's API key is saved
(`App\Services\ApiKeyChecker`). The valid-key answers reuse
`tests/Fixtures/catalogue/rakuten-isbn-9784592135746.json` and
`tests/Fixtures/catalogue/googlebooks-isbn-9780593820247.json`.

| File | Origin |
|---|---|
| `rakuten-wrong-application-id.json` | **Hand-written** 2026-11-15 from Rakuten's documented `400 wrong_parameter` error shape. |
| `googlebooks-invalid-key.json` | **Hand-written** 2026-11-15 from Google's documented `400 INVALID_ARGUMENT` "API key not valid" error. |
| `hardcover-invalid-token.json` | **Hand-written** 2026-11-15 from the Hasura `invalid-jwt` error Hardcover's GraphQL API answers (with HTTP 200) for a bad token. |
| `hardcover-me.json` | **Hand-written** 2026-11-15 from Hardcover's `me { id }` query; the id is made up. |

**Re-record them** (with a deliberately wrong key, and with a real one for `hardcover-me.json`)
and replace these files verbatim before relying on the exact messages:

```sh
curl "https://openapi.rakuten.co.jp/services/api/BooksBook/Search/20170404?format=json&formatVersion=2&isbn=9784592135746&applicationId=wrong&accessKey=wrong"
curl "https://www.googleapis.com/books/v1/volumes?q=isbn:9784592135746&maxResults=1&key=wrong"
curl -H "authorization: Bearer $HARDCOVER_TOKEN" -H 'content-type: application/json' \
  -d '{"query":"{ me { id } }"}' https://api.hardcover.app/v1/graphql
```
