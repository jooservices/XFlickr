---
name: class-purpose-and-module-map
description: Which XFlickr module owns what — Services, Repositories, Jobs, Pages.
---

# Skill: class-purpose-and-module-map

## Purpose

Route changes to the correct module and avoid cross-layer leaks.

## Laravel modules (`Modules/`)

SPA JSON API routes live under `Modules/*/routes/api.php` (`/api/v1`, `web`+`auth`). Web/Inertia routes live under `Modules/*/routes/web.php` (add `auth`/`guest` in the file — module `RouteServiceProvider` only applies `web`). Domain code (controllers, FormRequests, services, repositories, models, jobs, listeners, commands, enums) lives in modules. Thin `app/` remains for bootstrap and shared host utilities.

| Module | Owns |
|---|---|
| **Auth** | Session auth: login/logout, register (inactive until admin activate), forgot/reset password (token in `password_reset_tokens`, URL flashed—no email yet); `AuthService` / `UserService` / `UserRepository` / `PasswordResetTokenRepository`; CLI `xflickr:auth:activate-user`, `xflickr:auth:reset-password`; optional `AdminUserSeeder` |
| **Settings** | Runtime config / app-profile web controllers + FormRequests + `RuntimeConfigAdminService` / `ConfigEntryRepository` |
| **Operations** | Dashboard, ops snapshot/stream (web + API V1) + services |
| **Flickr** | Accounts, OAuth, crawl-runs, rate-limit, token-health + crawl/OAuth services + Flickr FormRequests/commands/events/support |
| **Contacts** | Contacts, annotations, contact-graph, expand/full-pass + Contact* services/models/requests/listeners/commands |
| **Catalog** | Photos / photosets / galleries / favorites + `CatalogQueryService` + catalog FormRequests/presenters |
| **Spider** | Spider runs / frontier + planner + spider models/enums/requests/listeners/commands |
| **Transfer** | Download/upload, stored-files + Photo*/Transfer* services + jobs + transfer models/enums/requests |
| **Storage** | Browse, OAuth, provider drivers + storage models/enums/contracts/requests/commands |

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
- Crawler table writes go through the crawler package API, not raw SQL in Services.
- New SPA API routes: `Modules/{Domain}/routes/api.php` + module `Http/Controllers/Api/V1/`.
- New Inertia/web routes: `Modules/{Domain}/routes/web.php` with explicit `auth`/`guest`.
- Controllers must not inject Repositories (deptrac).
- Register module Artisan commands on the module `*ServiceProvider::$commands` array.
- Artisan signatures: `xflickr:<module-alias>:<name>` (e.g. `xflickr:auth:reset-password`).

## Related skills

- `form-request-service-repository`
- `crawler-pipeline-integrity`
- `api-response-standards`
