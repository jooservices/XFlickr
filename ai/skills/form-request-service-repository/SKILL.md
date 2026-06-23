---
name: form-request-service-repository
description: Mandated Request → Controller → FormRequest → Service → Repository flow.
---

# Skill: form-request-service-repository

## Purpose

Enforce XFlickr's layered request lifecycle on all backend changes.

## Mandated flow

```
HTTP Request → Controller → FormRequest → Service → Repository → Model
Job → Service (only)
```

## Rules

### Controllers

- No `$request->validate()` inline
- No `Model::query()` or `DB::` calls
- Inject Services via constructor

### FormRequests

- Live in `app/Http/Requests/` grouped by domain
- Use shared concerns: `NormalizesPagination`, `NormalizesSorting`, `ResolvesCrawlTypes`
- `authorize()` must be explicit (return `true` only when appropriate)

### Services

- Single responsibility
- No bidirectional Service dependencies
- Query via Repository only

### Jobs

- `handle()` calls one Service method
- No business rules in Jobs

### Repositories

- Register in `RepositoryServiceProvider`
- Crawler reads: `app/Repositories/Crawler/`

## Related skills

- `architecture-and-design-principles`
- `class-purpose-and-module-map`
