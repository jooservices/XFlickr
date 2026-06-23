# Coding standards

XFlickr follows Laravel conventions with project-specific layering rules.

## Backend layering

Mandatory flow: `Controller → FormRequest → Service → Repository → Model`

See [Application standards](../00-architecture/application-standards.md).

### Controllers

- Thin — delegate to Services immediately.
- Group by feature area (`Flickr/`, `Storage/`, `Api/`).
- No inline validation, no query building.

### FormRequests

- One per meaningful input action.
- Shared concerns in `app/Http/Requests/Concerns/`.

### Services

- Single responsibility; no bidirectional Service dependencies.
- Constructor DI for widely used dependencies.

### Jobs

- Delegate all business logic to Services.
- `handle()` should be a thin call to a Service method.

### Repositories

- All Eloquent/DB queries live here.
- Crawler table reads: `app/Repositories/Crawler/`.

## PHP style

- Run Laravel Pint before committing: `vendor/bin/pint`
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
