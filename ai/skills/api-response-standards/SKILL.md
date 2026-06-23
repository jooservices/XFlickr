---
name: api-response-standards
description: JSON API response shapes for /api/* routes.
---

# Skill: api-response-standards

## Purpose

Keep `/api/*` endpoints consistent for frontend consumers.

## Routes

Defined in `routes/api.php`. Key groups:

- `/api/dashboard/snapshot`
- `/api/flickr/accounts/{connection}/crawl/*`
- `/api/catalog/*`
- `/api/transfers/*`
- `/api/storage/*`

## Conventions

- Return JSON with predictable top-level keys (`data`, `meta` for paginated lists).
- Use dedicated API controllers under `app/Http/Controllers/Api/`.
- Validate via FormRequests in `app/Http/Requests/Api/`.
- Query via Services and Repositories — not inline in controllers.

## Frontend consumption

- Pages poll API endpoints with `fetch()` or axios
- Unwrap `data` from response in page components
- Handle errors gracefully (Dashboard polling catches and ignores transient failures)

## Related skills

- `form-request-service-repository`
- `react-inertia-frontend`
