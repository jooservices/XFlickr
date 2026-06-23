# XCrawler plan audit standards (shared)

Apply these standards when reviewing any implementation plan under "## Plan under review".

## Audit priority (strict order)

1. **SOLID, DRY, KISS, YAGNI**
2. **Laravel / XCrawler application standard** (`AGENTS.md`, `docs/04-development/coding-standards.md`)
3. **Appropriate design patterns** — PHP: https://refactoring.guru/design-patterns/php ; React/Inertia: composition, hooks, thin components
4. **Current XCrawler code style** — lowest priority; do **not** recommend copying an existing smell if it violates 1–3

Architecture, boundary, and pattern violations are **in scope**. Formatting-only nits are out of scope unless they hide a principle breach.

## Engineering principles (mandatory)

Audit the plan against:

- **S**ingle Responsibility — one clear owner per behavior
- **O**pen/Closed — extend via existing services/events/contracts; no parallel persistence paths
- **L**iskov — contracts/DTOs substitutable without breaking callers
- **I**nterface segregation — thin module contracts; no god-interfaces
- **D**ependency inversion — Core depends on module contracts (`MovieSearchIndex`, etc.), not implementations

Also enforce:

- **DRY** — no duplicate persistence, search, or parsing paths
- **KISS** — smallest change that satisfies requirements
- **YAGNI** — no classes/layers/jobs/events for hypothetical futures

For **each new class** proposed, verify the plan answers the class-creation questions in `docs/04-development/coding-standards.md` and `xcrawler/ai/skills/class-purpose-and-module-map/SKILL.md`.

## Laravel / XCrawler standard

Verify:

```text
HTTP Request -> Controller -> FormRequest -> Service -> Repository/Index -> Model
```

- Controllers thin; business rules in services
- FormRequest owns validation/normalization
- Module boundaries: Crawler (parser only), Audit (logs), Discovery (ES), Maintenance (offline ops), Core (orchestration)
- No parser/business logic in React components or API controllers
- DTOs crossing boundaries extend `JOOservices\Dto\Core\Dto` where applicable
- Read relevant domain safety skills (catalog, search, crawl, etc.)

## PHP design pattern fit

Name the pattern (or "none") for each proposed class/layer. Flag misuse:

| Pattern | Accept when | Reject when |
|---------|-------------|-------------|
| Strategy | Multiple algorithms/grammars | Single fixed grammar (YAGNI) |
| Command | Action/nav payload shape only | Extra Command class for one route |
| Facade | Service orchestration | Duplicate Facade over existing service |
| Factory / Abstract Factory | Multiple product families | One consumer, one shape |
| Repository | Complex query composition | Single `where()` on Eloquent |
| Observer | After-commit side effects | Hidden sync side effects |
| Singleton | — | Almost always reject (use container) |

Reference: https://refactoring.guru/design-patterns/php

## React / Inertia (if plan touches `resources/js/`)

- No server-side business rules in components
- Prefer composition (existing `GlobalSearch`, `cmdk`, hooks)
- Types local or `@/types` only when truly shared
- Match `xcrawler/ai/skills/react-inertia-frontend/SKILL.md`
- Flag fat components, duplicated fetch/debounce, unnecessary new abstractions

## Test plan requirements (mandatory in plan)

The plan must define:

### Case matrix (all four required)

| Type | Purpose |
|------|---------|
| **Happy** | Expected success paths |
| **Unhappy** | Validation failures, empty results, denied/invalid input |
| **Weird** | Boundary combinations, stripped text, partial tokens |
| **Strange** | Misleading input, typos, case drift, almost-token text |

### Coverage targets (post-implementation)

- **Feature scope** (files listed in plan): **100%** line coverage required
- **Broader changed production code**: **minimum 90%** line coverage
- List exact file paths in the plan's coverage scope section

### Execution environment

- Tests must run in isolated **Docker test stack** (`bash scripts/docker-testing.sh gate`)
- Do not claim tests passed without real command output
- Report happy/unhappy/weird/strange cases with test names or manual smoke steps

## Scope

This workflow is **Cursor-only** (agent orchestration). Grok and Codex CLI audit/debate plans only; they do not implement code or replace the Docker quality gate.
