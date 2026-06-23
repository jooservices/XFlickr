# Application standards

XFlickr follows a layered request lifecycle for backend code.

## Mandated flow

```
HTTP Request
  → Controller (thin — no queries, no validation)
  → FormRequest (validation + authorization)
  → Service (business logic)
  → Repository (persistence queries)
  → Model / crawler tables
```

## Rules

### Controllers

- One action per route; delegate immediately to Services.
- No `$request->validate()` inline — use FormRequest classes in `app/Http/Requests/`.
- No `Model::query()` or raw DB access.

### FormRequests

- Live in `app/Http/Requests/` grouped by domain (`Flickr/`, `Storage/`, `Api/`, etc.).
- Use shared concerns (`NormalizesPagination`, `NormalizesSorting`, `ResolvesCrawlTypes`).

### Services

- Single responsibility; hold business rules.
- Depend on Repository interfaces, not Eloquent directly.
- Two Services must not depend on each other bidirectionally — use an orchestrator Service for shared workflows.

### Jobs

- Jobs are thin wrappers: resolve dependencies, call one Service method.
- No business logic in `handle()` beyond delegation and error reporting.

### Repositories

- App models: `app/Repositories/`
- Crawler read models: `app/Repositories/Crawler/`
- Registered in `RepositoryServiceProvider`.

## Dependency injection

- Constructor DI when a dependency is used across most methods of a class.
- Method-level `app($class)` only when a dependency is used in one method (document why).

## Frontend

- Pages in `resources/js/Pages/` compose shared `Components/`.
- Use existing `Button`, `CrawlActionBar`, `DataTable`, `PageHeading` patterns.
- See [Frontend standards](../04-development/frontend-standards.md).
