# library.gekophetweb.nl — agent instructions

Laravel 13 app (PHP 8.4) for a personal catalogue of physical books, music and games.

- Project rules: [.ai/guidelines/physical-library.md](.ai/guidelines/physical-library.md)
- Feature plans: [.ai/plans/](.ai/plans/)
- Commit and branch workflow: [.ai/skills/committing-and-pushing/SKILL.md](.ai/skills/committing-and-pushing/SKILL.md)

## Running tests and Pint

PHP runs in Docker (the image has `intl`, which the series code needs). With the
compose stack up:

```sh
docker compose exec web php vendor/bin/pest --compact
docker compose exec web php vendor/bin/pint --dirty --format agent
```

Without the stack (no `.env` or database needed; dev dependencies must be in `vendor/`):

```sh
docker compose -f docker-compose.test.yml run --rm test
docker compose -f docker-compose.test.yml run --rm test php vendor/bin/pint --dirty --format agent
```

Tests never call external APIs: recorded responses live in `tests/Fixtures/` and are
replayed with `Http::fake()`; `Http::preventStrayRequests()` is on for feature tests.
