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
Job → Service (and Repositories only when claiming/updating work-item rows — never Model::query())
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
- **Never** call Eloquent from a Service: no `Model::query()`, `Model::create()`, `Model::updateOrCreate()`, `Model::firstOrCreate()`, `$model->update()`, `$model->save()`, `$model->delete()`, or domain-table `DB::table(...)` — **persist and query only through Repositories** (prefer model scopes inside the repository)
- `DB::transaction(...)` wrapping Repository calls is allowed
- Controllers and FormRequests also never touch Models/Repositories for persistence (Controller → Service → Repository)

### Jobs / Commands

- Live in `Modules/*/Jobs/` and `Modules/*/Console/Commands/`
- Commands: `handle()` calls one Service method only — never Eloquent
- Jobs: business outcomes via Services; work-item claim/status updates via Repositories — never `Model::query()` / `Model::create()` in Jobs
- No business rules in Jobs or Commands beyond orchestrating Service/Repository calls

### Repositories

- Module-owned: `Modules/*/Repositories/`
- Shared crawler reads: `app/Repositories/Crawler/` (host query side for UI)
- Register in module `RepositoryRegistrar` / host `RepositoryServiceProvider`
- Prefer meaningful Eloquent **model scopes** for reusable query fragments (e.g. `Connection::scopeByConnectionKey` → `$query->byConnectionKey($key)` in the repository). Do not duplicate raw `where(...)` for the same filter across callers.

### Models

- Own casts, relationships, and local/global scopes
- No Service/Repository imports (deptrac)
- Add a named scope when a filter is meaningful and reusable (identity keys, ownership, status) — not for one-off ad hoc conditions

## Anti-patterns (reject in review)

```php
// BAD — in a Service
Connection::query()->where('connection_key', $key)->first();
$run->update([...]);

// GOOD
$this->connections->findByKey($key);
$this->crawlRuns->update($run, [...]);
```

```php
// BAD — in a Command
ApiLog::query()->where('created_at', '<', $cutoff)->delete();

// GOOD
$prune->pruneApiLogsOlderThanDays($days);
```

## Module guard (Crawler)

`Modules/Crawler/tests/Unit/Architecture/CrawlerLayeringTest` fails if Services/Console/Jobs under `Modules/Crawler/app` call `Model::query()` / `::create()` / `::updateOrCreate()` / `::firstOrCreate()`. Extend the same pattern when hardening other modules.

## Related skills

- `architecture-and-design-principles`
- `class-purpose-and-module-map`
- `crawler-pipeline-integrity`
