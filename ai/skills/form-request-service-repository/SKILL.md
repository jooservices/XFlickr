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
Console Command → Service → Repository → Model
Job → Service (only)
```

Every mutating or input-bearing HTTP action **must** use a dedicated FormRequest class — including body-less POSTs (e.g. logout). Controllers never accept bare `Illuminate\Http\Request` for those actions.

Domain classes live under `Modules/{Name}/`. Thin `app/` holds shared FormRequest base/traits, crawler query repos, and bootstrap.

## Rules

### Controllers

- No `$request->validate()` inline
- No `Model::query()` or `DB::` calls
- No Repository injection (deptrac)
- No business logic (auth attempts, session invalidate, hashing, etc.)
- Inject Services via constructor
- JSON API: `BaseApiController` + `JsonResource`

### FormRequests

- Live in `Modules/{Domain}/Http/Requests/`
- One dedicated class per action (`LoginRequest`, `LogoutRequest`, …)
- Shared host traits: `app/Http/Requests/Concerns/{NormalizesPagination,NormalizesSorting,ResolvesCrawlTypes}`
- Storage account resolution: `Modules/Storage/Http/Requests/Concerns/ResolvesStorageAccount`
- `authorize()` must be explicit (return `true` only when appropriate)
- Validation + accessors only — no Auth::attempt, no repositories, no session mutation

### Services

- Single responsibility under `Modules/*/Services/`
- Own all business rules
- No bidirectional Service dependencies
- Query via Repository only (when persistence is involved)

### Jobs / Commands

- Live in `Modules/*/Jobs/` and `Modules/*/Console/Commands/`
- `handle()` calls one Service method
- No business rules in Jobs or Commands

### Repositories

- Module-owned: `Modules/*/Repositories/`
- Shared crawler reads: `app/Repositories/Crawler/`
- Register in `RepositoryServiceProvider`
- Prefer meaningful Eloquent **model scopes** for reusable query fragments (e.g. `User::scopeByEmail` → `$query->byEmail($email)` in the repository). Do not duplicate raw `where(...)` for the same filter across callers.

### Models

- Own casts, relationships, and local/global scopes
- No Service/Repository imports (deptrac)
- Add a named scope when a filter is meaningful and reusable (identity keys, ownership, status) — not for one-off ad hoc conditions

## Related skills

- `architecture-and-design-principles`
- `class-purpose-and-module-map`
