# AI Development Workflow

XFlickr uses a **Cursor-orchestrated**, plan-before-code workflow for non-trivial feature work. External CLIs audit plans and diffs; **Cursor** implements, fixes, runs tests, syncs docs, and reports.

Canonical skill: [`../../ai/skills/multi-llm-plan-review/SKILL.md`](../../ai/skills/multi-llm-plan-review/SKILL.md).

## Roles

| Agent | Role |
|---|---|
| **Cursor** | Orchestrator, implementer, test runner, documentation sync |
| **Grok CLI** | Adversarial plan reviewer (read-only) |
| **Codex CLI** | Plan debate (read-only) |
| **GitHub Copilot CLI** | Post-gate diff reviewer (medium+ tiers) |
| **Human** | Approves `final_plan.md` and final implementation |

## Two Docker stacks

| Stack | Compose file | Use |
|---|---|---|
| **Test** | `docker-compose.test.yml` | `composer test:docker` — mandatory quality gate |
| **Local dev** | `docker-compose.yml` | User data — **agents: no DB commands** |

Never run `php artisan test` or migrations on the local dev stack.

## Tier routing

| Tier | Examples | Grok + Codex | Copilot |
|---|---|---|---|
| Small | Typo, one-line fix | Skip | Skip |
| Medium | Endpoint, service, migration | Yes | After green gate |
| Large | Crawl, transfer, storage, cross-module | Yes | After green gate |

Critical triggers: crawl pipeline, download/upload dedup, storage OAuth, Docker safety.

## End-to-end flow

```text
Request feature
  → Cursor writes ai/plans/plan.md
  → bash scripts/plan-review.sh (Grok + Codex)
  → Cursor evidence-gates ai/plans/final_plan.md
  → STOP #1: human approves final_plan.md

  → Cursor implements
  → composer test:docker (mandatory)
  → npm run typecheck (if frontend changed)
  → bash scripts/implementation-review.sh (medium+)
  → Documentation sync + CHANGELOG [Unreleased]
  → STOP #2: human approves (optional for small changes)
  → Commit (user request only)
  → Delete ephemeral ai/plans/*.md
```

## Phase 1 — Planning

### Write `ai/plans/plan.md`

Include scope, non-scope, architecture, file list, ordered steps, test matrix, risks.

**Do not implement** until `final_plan.md` is approved (medium+ tiers).

### External plan audits

```bash
bash scripts/plan-review.sh
```

Outputs: `ai/plans/plan_grok.md`, `ai/plans/plan_codex_debate.md`

Skip if `grok`/`codex` not on PATH — document reason in plan.

### Evidence-gated `ai/plans/final_plan.md`

Adjudicate findings: **ACCEPTED** / **REJECTED** / **DEFERRED**.

## Phase 2 — Implementation

### Quality gate (mandatory)

```bash
composer test:docker
npm run typecheck    # when frontend changed
composer instructions:verify
```

### Copilot review (medium+)

```bash
bash scripts/implementation-review.sh
```

Requires `copilot` on PATH and `ai/plans/final_plan.md`.

## Phase 3 — Documentation sync

Update affected docs, `CHANGELOG.md` `[Unreleased]`, and run `composer instructions:verify`.

## Ephemeral artifacts

`ai/plans/*.md` is gitignored except `.gitkeep`. Delete plan files after commit.

## Optional CLIs

| CLI | Required for |
|---|---|
| `grok` | Plan audit |
| `codex` | Plan debate |
| `copilot` | Implementation review |

When unavailable, Cursor performs manual review using `review-and-risk-assessment` skill.
