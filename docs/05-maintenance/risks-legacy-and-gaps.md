# Risks, legacy, and gaps

Living document of known gaps and partial implementations. Update when status changes.

## Resolved since initial audit

| Item | Status |
|---|---|
| FormRequest layer | Implemented — `app/Http/Requests/` |
| Repository layer | Implemented — `app/Repositories/` |
| Jobs delegate to Services | Largely implemented — verify per-job |
| User authentication | Session login (`admin@local` seeded); all routes require auth; Horizon gated |
| Structured audit logging | Settings/credential changes via `jooservices/laravel-logging` |
| Domain events | Flickr/storage connect/disconnect via `jooservices/laravel-events` |
| Deprecated contact batch jobs | Removed — use `PhotoDownloadService` / `PhotoUploadService` directly |

See [codebase audit (2026-06)](codebase-audit-2026-06.md) for original findings.

## Open gaps

| Area | Gap | Priority |
|---|---|---|
| Security | Flickr OAuth tokens plaintext in crawler package (`xflickr-crawler`) | High |
| Backend | Transfer pipeline scale/reliability at high volume (deferrals, reconciler race) | Medium |
| Docs | No OpenAPI spec for `/api/*` routes | Low |
| CI | Coverage gate not yet enforced | Low |

## Moved to resolved (2026-06 audit follow-up)

| Item | Status |
|---|---|
| Centralized API client (`resources/js/lib/apiClient.ts`) | Resolved |
| ESLint configuration (`eslint.config.js`, `npm run lint`) | Resolved — wired in CI (2026-07) |
| PHPStan in composer scripts (`composer lint:phpstan`) | Resolved — wired in CI (2026-07); level uplift tracked in release audits |
| Duplicated catalog filter state | Largely resolved via shared hooks |
| Residual controller query logic (audit #3) | Largely resolved — catalog/crawl APIs delegate to services |
| Job/service duplication (audit #4–#6) | Resolved — execution services + deprecated jobs removed |

## Architectural constraints

- **Manual crawl only** — by design; do not add auto-spidering without explicit product decision.
- **Flickr API quota** — rate limits are a hard constraint; bulk operations need UX warnings.
- **Two Docker stacks** — dev data is precious; test stack is mandatory for agents.

## Stale documentation

Historical planning docs live in [`archive/`](archive/). Multi-LLM release audits: [`release-reviews/2026-07-06/`](release-reviews/2026-07-06/). Do not treat them as current architecture.

## Reporting new gaps

Add a row to this table or open a GitHub issue with the `documentation` label.
