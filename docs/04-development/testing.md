# Testing

XFlickr uses PHPUnit. All tests run in the **isolated Docker test stack only**.

## Run tests

```bash
composer test:docker
./scripts/test-docker.sh --filter=ExampleTest
```

Equivalent:

```bash
docker compose -f docker-compose.test.yml run --rm test
```

## Forbidden

**Never** run tests on the local dev stack:

```bash
# FORBIDDEN — wipes dev MySQL via RefreshDatabase
docker compose exec app php artisan test
php artisan test   # when pointed at dev .env
```

## Test stack

- Compose file: `docker-compose.test.yml`
- MySQL: SQLite `:memory:`
- MongoDB: `xflickr_test`
- Entrypoint: `docker/test-entrypoint.sh`

## Writing tests

- Feature tests in `tests/Feature/` for HTTP, jobs, and integration flows.
- Unit tests in `tests/Unit/` for isolated logic.
- Use factories and existing test helpers.
- Mock external APIs (Flickr, cloud storage) — do not call real services in tests.

## Frontend

```bash
npm run typecheck
```

No Jest/Vitest suite yet — TypeScript checking is the frontend gate.

## Related

- Skill: `xflickr-docker-testing`, `testing-and-quality-gates`
- [Docker stacks](../03-operations/docker-stacks.md)
