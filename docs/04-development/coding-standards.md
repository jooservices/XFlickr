# Coding standards

XFlickr follows Laravel conventions with project-specific layering rules.

## Backend layering

Mandatory flow: `HTTP Request → Controller → FormRequest → Service → Repository → Model`

Artisan: `Command → Service → Repository → Model`. See [Application standards](../00-architecture/application-standards.md).

### Controllers

- Thin — delegate to Services immediately.
- Dedicated FormRequest per action (including body-less logout).
- No inline validation, no query building, no business logic.

### FormRequests

- One per meaningful input action (validation + authorize + accessors only).
- Shared concerns in `app/Http/Requests/Concerns/`.

### Services

- Single responsibility; no bidirectional Service dependencies.
- Constructor DI for widely used dependencies.

### Jobs / Commands

- Delegate all business logic to Services.
- `handle()` should be a thin call to a Service method.
- Command signatures: `xflickr:<module-alias>:<name>`.

### Repositories

- All Eloquent/DB queries live here.
- Crawler table reads: `app/Repositories/Crawler/`.
- Reuse meaningful **model scopes** instead of repeating raw `where` clauses (e.g. `->byEmail($email)`).

### Models

- Local scopes for reusable, named filters (`scopeByEmail`, ownership, status).
- No business orchestration or container calls.

## PHP style

- Laravel Pint is the formatting authority.
- PHPCS performs non-duplicative PHP source checks after Pint.
- PHPStan, PHPMD, and Deptrac enforce types, maintainability, and architecture.
- Run all PHP quality checks through `bash scripts/test.sh gate:lint`.
- `declare(strict_types=1);` on new PHP files
- Prefer typed properties and return types

## Frontend

See [Frontend standards](frontend-standards.md).

## Inspect before inventing

Do not invent routes, commands, or config keys. Read existing source and docs first.

## Related skills

- `form-request-service-repository`
- `architecture-and-design-principles`
- `code-style-and-conventions`
