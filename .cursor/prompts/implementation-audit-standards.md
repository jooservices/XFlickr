# XCrawler implementation audit standards (shared)

Apply these standards when reviewing an implementation against an approved plan and git diff.

## Audit priority (strict order)

1. **Compliance with `final_plan.md`** — scope, architecture, file list, module boundaries
2. **SOLID, DRY, KISS, YAGNI** and Laravel / XCrawler standards (`AGENTS.md`)
3. **Data integrity** — `syncWithoutDetaching`, `TaxonomyService`, `UrlHash`, after-commit indexing, fresh model reloads
4. **Security and validation** — FormRequest ownership, no secrets in diff, SSRF/HTML safety
5. **Tests** — happy / unhappy / weird / strange matrix from the plan; no missing regression coverage
6. **Out-of-scope changes** — unrelated files, drive-by refactors, parallel persistence paths

## Module boundaries

- **Modules/Crawler** — parser/adapter only; no Core dependencies
- **Modules/Audit** — logging only; no Core service imports
- **Modules/Discovery** — Elasticsearch only; no catalog writes
- **Modules/Maintenance** — offline repair; uses Core services, no parallel paths
- **Core** — orchestration, dashboard, persistence

## Review inputs

You receive:

1. Approved `final_plan.md` (locked specification)
2. Git diff (implementation changes)
3. Implementation verification report (test gate results)

## Output rules

- **Review only** — do not modify source files or the plan
- Mark unverified claims as **To verify**
- Flag P0 risks: data loss, destructive ES/DB operations, `sync()` on crawl pivots, module boundary violations
- Prefer actionable findings with file paths and evidence

## Out of scope

- Formatting-only nits unless they hide a principle breach
- Suggesting features not in `final_plan.md`
- Re-running or claiming tests passed without evidence in the verification report
