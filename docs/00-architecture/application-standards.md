# Application standards

XFlickr follows a layered request lifecycle for backend code. Domain code lives in `Modules/{Name}` (`nwidart/laravel-modules`); thin `app/` is host bootstrap only.

Which module owns what: [Modules catalog](modules.md).

## Mandated flow

```
HTTP Request
  → Controller (thin — no queries, no validation, no business logic)
    → FormRequest (validation + authorization only; dedicated class per action)
      → Service (business logic)
        → Repository (persistence queries)
          → Model / crawler tables
```

Artisan: `Command → Service → Repository → Model`. Jobs: `Job → Service` only.

## Rules

Value objects / result DTOs live under `Modules/*/Dto/` (or host `app/Dto/`); behavior stays in `Services/`; static helpers in `Support/`.

### Controllers

- One action per route; delegate immediately to Services.
- No `$request->validate()` inline — use FormRequest classes in `Modules/*/Http/Requests/` (shared base/traits remain under `app/Http/Requests/`).
- No bare `Illuminate\Http\Request` on mutating actions — always a dedicated FormRequest (including body-less POSTs such as logout).
- No `Model::query()` or raw DB access.
- Controllers must not inject Repositories (deptrac).
- JSON API controllers extend `JOOservices\LaravelController\Http\Controllers\BaseApiController` and return Laravel `JsonResource` payloads via package helpers (`success`, `error`, pagination) — same standard as XCrawlerII.

### FormRequests

- Live in `Modules/{Domain}/Http/Requests/` (API subgroup `Api/` when needed).
- Shared host concerns: `NormalizesPagination`, `NormalizesSorting`, `ResolvesCrawlTypes` in `app/Http/Requests/Concerns/`.
- Storage account resolution: `Modules/Storage/Http/Requests/Concerns/ResolvesStorageAccount`.
- No Auth attempts, repository calls, or session mutation inside FormRequests.

### Services

- Single responsibility; hold business rules under `Modules/*/Services/`.
- Depend on Repositories for all persistence — **never** Eloquent/`Model::query()` / `$model->update()` from Services (or Controllers/Commands).
- `DB::transaction()` wrapping Repository calls is fine.
- Operations DB introspection is a sanctioned exception: `DatabaseUsageService` and `ServicesDependencyProbeService` may use `DB::select` / driver status queries for dashboard probes (enforced via whitelist in `tests/Unit/Architecture/ServiceLayeringTest`).
- Two Services must not depend on each other bidirectionally — use an orchestrator Service for shared workflows.

### Commands / Jobs

- Commands: `Command → Service → Repository → Model` only.
- Jobs: call Services; work-item claim/status via Repositories — never static Eloquent query builders in Jobs.

### Jobs / Commands

- Jobs are thin wrappers under `Modules/*/Jobs/`: resolve dependencies, call one Service method.
- Commands are thin under `Modules/*/Console/Commands/`; signatures `xflickr:<module>:<name>`; call Services only.
- Register Artisan commands on the module `*ServiceProvider::$commands` array.

### Repositories

- Module-owned: `Modules/*/Repositories/`
- Shared crawler reads: `app/Repositories/Crawler/`
- Registered in `RepositoryServiceProvider`.
- Prefer meaningful Eloquent model scopes for reusable filters (repositories call scopes; they do not re-encode the same `where` everywhere). Predicates that repeat ≥3 times or name a domain state become classic `scopeX` methods; repositories compose those scopes.

### Models

- Casts, relationships, and named local scopes for reusable query fragments. Predicates that repeat ≥3 times or name a domain state become classic `scopeX` methods (not `#[Scope]`); repositories compose those scopes.
- No Service/Repository imports (deptrac Model layer is empty of outbound deps).

## Dependency injection

- Constructor DI when a dependency is used across most methods of a class.
- Method-level `app($class)` only when a dependency is used in one method (document why).

## Laravel modules

Nine business modules: Auth, Settings, Operations, Flickr, Contacts, Catalog, Spider, Transfer, Storage.

- SPA JSON APIs: `Modules/*/routes/api.php` under `/api/v1` with `web`+`auth`.
- Web/Inertia: `Modules/*/routes/web.php` (explicit `auth`/`guest`).
- Root `routes/web.php` and `routes/api.php` are stubs.
- Ownership map: [class-purpose-and-module-map](../../ai/skills/class-purpose-and-module-map/SKILL.md).

## Observability

Hybrid layers — do not collapse them into one mechanism:

| Concern | Mechanism | Destination |
|---|---|---|
| Cross-module workflow milestones | Domain events | Peer module listeners (Spider, Contacts, Operations) |
| Durable operator / domain trail | `ActivityLog::{audit,domain,system}` (`jooservices/laravel-logging`) | Mongo `activity_logs` |
| Ops diagnosis (retries, cooldown, worker noise) | Redacted `Log::*` via module Observability wrappers | Laravel logging channels |
| Flickr API call outcomes | `FlickrApiAuditService` | MySQL `xflickr_api_logs` |
| Admin config mutations | `AdminActionLogger` | `ActivityLog::audit()` |

Rules:

- Prefer domain events for cross-module milestones (`CrawlRunStarted` / `Completed` / `Failed`, `ContactsCrawlCompleted`, etc.).
- Prefer `ActivityLog` for durable, queryable history; Crawler writes through `CrawlerObservability`.
- Prefer redacted `Log::*` (never raw keys/tokens/bodies — use `ThirdPartyApiLogger::fingerprint()`) for infra/ops noise.
- Correlation: use natural IDs (`crawl_run_id` as `correlationId` / `batchId`; `spider_run_id` as `workflowId` when present).
- Operator UI: `/activity` (Operations) reads Mongo `activity_logs` via `GET /api/v1/operations/activities`.
- Explicit non-goal: full event sourcing for crawl/target state (account connect/disconnect already use `laravel-events` sourcing).

## Frontend

- Pages in `resources/js/Pages/` compose shared `Components/` inside **AppShell** (master) + **PageShell** (content).
- Use JOO packages (`@jooservices/react-layout`, `react-content`, `react-table`) via thin barrels; keep domain macros for crawl/graph/transfer.
- Poll with `usePolledResource` and `/api/v1` paths from `lib/apiPaths.ts`.
- See [Frontend standards](../04-development/frontend-standards.md).
