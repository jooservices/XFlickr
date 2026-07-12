---
name: api-response-standards
description: JSON API response shapes for /api/v1/* routes.
---

# Skill: api-response-standards

## Purpose

Keep `/api/v1/*` endpoints consistent for frontend consumers. JSON API is **RESTful** under the `/api/v1` prefix — no imperative URI verbs (`delete`, `start`, `stop`, `crawl`, `expand`, …).

## Routes

Defined in `routes/api.php` (moving into `Modules/*/routes/api.php` over time). Key groups:

- `/api/v1/dashboard/snapshot`
- `/api/v1/operations/snapshot`, `/api/v1/operations/stream`
- `/api/v1/flickr/accounts/{connection}/crawl-runs`
- `/api/v1/flickr/accounts/{connection}/contact-graph`
- `/api/v1/flickr/catalog/*`
- `/api/v1/flickr/accounts/{connection}/transfers/*`
- `/api/v1/storage/{provider}/files`, `/api/v1/storage/{provider}/sync-runs`

## Conventions

- Return JSON via `JOOservices\LaravelController\Http\Controllers\BaseApiController` (`success` / `error` / pagination helpers). Envelope keys: `success`, `code`, `message`, `data`, `meta`, `trace_id`, …
- Controllers live under module `Http/Controllers/Api/V1/` (or remaining `app/Http/Controllers` for Inertia) and **must** extend `BaseApiController` for JSON.
- Shape success payloads with Laravel **`JsonResource`** classes (XCrawlerII Plugin pattern): `$this->success(FooResource::make($model))`, `$this->success(FooResource::collection($rows), meta: $meta)`, or keyed nests like `['account' => FlickrAccountResource::make(...)]`. Package `normalizeResponseValue()` resolves nested Resources.
- Put Resources in `Modules/{Name}/app/Http/Resources/` when the controller lives in that module; otherwise `app/Http/Resources/{Domain}/`.
- Validate via FormRequests in `app/Http/Requests/Api/`.
- Query via Services and Repositories — not inline in controllers. Services return models/DTOs/arrays; **Resources own presentation** for JSON responses.
- Controllers must not inject Repositories (Controller → Service → Repository).

## Frontend consumption

- Use `apiGet` / `apiPost` / `apiPatch` / `apiDelete` from `resources/js/lib/apiClient.ts`.
- Build paths with `API_V1` / `flickrApiAccountPath` (`/api/v1/...`).
- Pages use master `AppShell` + content `PageShell`; poll with `usePolledResource` where appropriate.

## Related skills

- `form-request-service-repository`
- `react-inertia-frontend`
