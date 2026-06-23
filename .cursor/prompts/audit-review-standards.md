# Monorepo audit review standards (shared)

Apply when reviewing the audit document under "## Audit under review".

## Priority (strict order)

1. **Factual accuracy** — every claim must be verifiable from source or marked "To verify"
2. **Monorepo layout** — paths must use `xcrawler/` prefix for Laravel app code; root holds monorepo shell only
3. **Data integrity** — crawl/catalog/search safety; no advice that risks downloads, genres, performers, ES data
4. **SOLID / DRY / KISS / YAGNI** — diagnoses must match cited classes, not generic platitudes
5. **Actionability** — punch list items must be scoped, ordered, and proportionate

## Required context

Before challenging or confirming findings, consider:

- [`AGENTS.md`](AGENTS.md) — monorepo router
- [`xcrawler/AGENTS.md`](xcrawler/AGENTS.md) — Laravel/module boundaries
- [`xcrawler/ROADMAP.md`](xcrawler/ROADMAP.md) — for interface/future-provider decisions
- [`skills/review-and-risk-assessment/SKILL.md`](skills/review-and-risk-assessment/SKILL.md)
- [`xcrawler/docs/04-development/coding-standards.md`](xcrawler/docs/04-development/coding-standards.md)

## Severity when reviewing the audit

| Level | Meaning |
|-------|---------|
| **P0** | Audit claim could cause data loss or destructive ops if followed blindly |
| **P1** | Wrong path, wrong class name, or misleading fix that wastes significant effort |
| **P2** | Incomplete, overstated, or low-value recommendation |

## Scope

Reviewers audit **audit.md only**. They do not implement fixes or replace the Docker quality gate.
