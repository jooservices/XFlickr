# Risks, legacy, and gaps

Living document of known gaps and partial implementations. Update when status changes.

## Resolved since initial audit

| Item | Status |
|---|---|
| FormRequest layer | Implemented — `app/Http/Requests/` |
| Repository layer | Implemented — `app/Repositories/` |
| Jobs delegate to Services | Largely implemented — verify per-job |

See [codebase audit (2026-06)](codebase-audit-2026-06.md) for original findings.

## Open gaps

| Area | Gap | Priority |
|---|---|---|
| Backend | Residual query logic in some controllers (see audit issue #3) | Medium |
| Backend | Job/service duplication in transfer flows (audit issues #4–#6) | Medium |
| Docs | No OpenAPI spec for `/api/*` routes | Low |
| Auth | No user authentication — single-operator deployment assumed | Info |

## Moved to resolved (2026-06 audit follow-up)

| Item | Status |
|---|---|
| Centralized API client (`resources/js/lib/apiClient.ts`) | Resolved |
| ESLint configuration (`eslint.config.js`, `npm run lint`) | Resolved |
| PHPStan in composer scripts (`composer lint:phpstan`) | Resolved — not yet in CI |
| Duplicated catalog filter state | Largely resolved via shared hooks |

## Architectural constraints

- **Manual crawl only** — by design; do not add auto-spidering without explicit product decision.
- **Flickr API quota** — rate limits are a hard constraint; bulk operations need UX warnings.
- **Two Docker stacks** — dev data is precious; test stack is mandatory for agents.

## Stale documentation

Historical planning docs live in [`archive/`](archive/). Do not treat them as current architecture.

## Reporting new gaps

Add a row to this table or open a GitHub issue with the `documentation` label.
