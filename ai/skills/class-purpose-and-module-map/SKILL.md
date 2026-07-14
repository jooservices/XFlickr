---
name: class-purpose-and-module-map
description: Which XFlickr module owns what — Services, Repositories, Jobs, Pages.
---

# Skill: class-purpose-and-module-map

## Purpose

Route changes to the correct module and avoid cross-layer leaks.

## When to use

Any change that adds routes, controllers, services, models, jobs, commands, or Inertia pages — or when unsure which `Modules/{Name}` owns a feature.

## Required context

- Canonical catalog (scope, purpose, features): [`docs/00-architecture/modules.md`](../../../docs/00-architecture/modules.md)
- Package vs crawler: [`docs/00-architecture/package-boundaries.md`](../../../docs/00-architecture/package-boundaries.md)
- `AGENTS.md` architecture boundaries

## Laravel modules (`Modules/`)

SPA JSON API routes live under `Modules/*/routes/api.php` (`/api/v1`, `web`+`auth`). Web/Inertia routes live under `Modules/*/routes/web.php` (add `auth`/`guest` in the file — module `RouteServiceProvider` only applies `web`). Domain code (controllers, FormRequests, services, repositories, models, jobs, listeners, commands, enums) lives in modules. Thin `app/` remains for bootstrap and shared host utilities.

| Module | Scope | Purpose | Key features |
|---|---|---|---|
| **Auth** | Session identity + local `User` lifecycle (not Flickr/storage OAuth) | Gate SPA/API; register → activate → login; password reset | Login/logout, register, forgot/reset (token URL, no email yet); CLI `xflickr:auth:activate-user`, `xflickr:auth:reset-password`; `AdminUserSeeder` |
| **Settings** | Runtime config + OAuth **app** credentials | Operator knobs and API keys in MongoDB (`xflickr_app.*`, `storage_app.*`) | `/settings`; runtime config CRUD/reset; Flickr/storage app profiles |
| **Operations** | Read-only ops aggregation (imports Flickr/Transfer; reverse forbidden) | Live health and activity console | Dashboard; Operations Overview/Crawl/Transfers; API snapshot + stream |
| **Flickr** | Connected Flickr **user** accounts + crawl triggers | Bridge host ↔ crawler via `connection_key` | OAuth connect/disconnect/activate; crawl-runs; rate-limit; token-health; CLI doctor/audit |
| **Contacts** | Contact directory / graph / full-pass on crawler contact data | Explore, annotate, expand, full-pass contacts | List/detail, annotations, contact-graph, expand-previews, full-pass start/stop; CLI expand |
| **Catalog** | Catalog **browse** UI/API (data in crawler tables) | View indexed photos/sets/galleries/favorites | Photos, photosets, galleries, favorites web + `/api/v1/flickr/catalog/*` |
| **Spider** | Opt-in frontier expansion (`spider.enabled`) | Multi-contact discovery beyond manual targets | Expand start/stop on Flickr accounts; current run status; planner + frontier models |
| **Transfer** | Download/upload orchestration + `stored_files` | Move originals to disk/cloud with progress/dedup | Enqueue download/upload; transfer progress/retry; stored-file show |
| **Storage** | Cloud **user** accounts + provider drivers | Connect destinations; browse/sync/delete remote files | OAuth/R2; browse pages; API browse/sync/delete/download |

## Remaining under `app/` (host)

| Area | Path | Owns |
|---|---|---|
| Base controller | `app/Http/Controllers/Controller.php` | Empty base (legacy) |
| Shared FormRequest base + pagination/sort/crawl traits | `app/Http/Requests/` | Cross-module validation helpers |
| Crawler query repos | `app/Repositories/Crawler/` | Shared crawler table reads |
| User model | `app/Models/User.php` | Auth identity (`scopeByEmail`); tested via Auth module suites |
| Shared Support | `app/Support/{Query,Observability,...}` | Host utilities |
| Providers / Middleware | `app/Providers`, `app/Http/Middleware` | Bootstrap |
| Frontend | `resources/js/Pages/`, `Components/` | Inertia UI (`AppShell` + `PageShell`) |

Root `routes/web.php` and `routes/api.php` are stubs; modules own route registration. There is no `app/Services/`, `app/Jobs/`, or domain `app/Models/` tree (except `User`).

## Rules

- Do not put business logic in Controllers, FormRequests, Jobs, or Commands — Services own it.
- Every HTTP action with a FormRequest-eligible verb uses a **dedicated** FormRequest (including body-less logout).
- Prefer meaningful **model scopes** for reusable query filters; repositories compose scopes instead of repeating raw `where` clauses.
- Crawler table writes go through the Crawler module API (`FlickrService` / manager), not raw SQL in Services.
- New SPA API routes: `Modules/{Domain}/routes/api.php` + module `Http/Controllers/Api/V1/`.
- New Inertia/web routes: `Modules/{Domain}/routes/web.php` with explicit `auth`/`guest`.
- Controllers must not inject Repositories (deptrac).
- Register module Artisan commands on the module `*ServiceProvider::$commands` array.
- Artisan signatures: `xflickr:<module-alias>:<name>` (e.g. `xflickr:auth:reset-password`).
- Before inventing a new module or host service, check [`docs/00-architecture/modules.md`](../../../docs/00-architecture/modules.md).

## Related skills

- `form-request-service-repository`
- `crawler-pipeline-integrity`
- `transfer-pipeline-safety`
- `storage-driver-safety`
- `api-response-standards`
- `documentation-sync`
