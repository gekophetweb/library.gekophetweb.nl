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

Without the stack, mount the checkout into the built image (dev dependencies must be
installed in `vendor/`):

```sh
docker build --target runtime -t library-gekophetweb/app:latest .
docker run --rm -u root -e PHP_OPCACHE_ENABLE=0 -v "$PWD:/var/www/html" library-gekophetweb/app:latest php vendor/bin/pest --compact
```

Tests never call external APIs: recorded responses live in `tests/Fixtures/` and are
replayed with `Http::fake()`; `Http::preventStrayRequests()` is on for feature tests.
