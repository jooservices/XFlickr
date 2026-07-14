# AI documentation map (XFlickr)

This folder documents **how AI tools use XFlickr** — not application runtime code.

## Read first

1. [`AGENTS.md`](../AGENTS.md) — non-negotiable rules for every agent
2. [`ai/skills/`](skills/) — canonical skill bodies
3. [Documentation hub](../docs/README.md)
4. [AI development workflow](../docs/04-development/ai-development-workflow.md)

Copilot stubs (index only): [`.github/skills/`](../.github/skills/)

## By tool

| Tool | Entry | Role |
|---|---|---|
| **Cursor** | `AGENTS.md`, `.cursor/rules/`, `ai/skills/` | Orchestrator, implementer, test runner |
| **Claude** | [`CLAUDE.md`](../CLAUDE.md) → `AGENTS.md` | Same rules as Cursor |
| **GitHub Copilot** | [`.github/copilot-instructions.md`](../.github/copilot-instructions.md), [`.github/prompts/`](../.github/prompts/) | IDE assist and chat templates |
| **Grok CLI** | `scripts/plan-review.sh` + [`.cursor/prompts/plan-audit-grok.md`](../.cursor/prompts/plan-audit-grok.md) | Adversarial plan audit |
| **Codex CLI** | `scripts/plan-review.sh` + [`.cursor/prompts/plan-debate-codex.md`](../.cursor/prompts/plan-debate-codex.md) | Plan debate / bug hunt |
| **Copilot CLI** | `scripts/implementation-review.sh` + [`.cursor/prompts/implementation-audit-copilot.md`](../.cursor/prompts/implementation-audit-copilot.md) | Post-gate diff review |

## Canonical skills (21)

Use `ai/skills/` only — do not duplicate skill bodies elsewhere.

### Foundation and governance

- [`repo-quality-foundation`](skills/repo-quality-foundation/SKILL.md) — baseline quality and scope discipline
- [`architecture-and-design-principles`](skills/architecture-and-design-principles/SKILL.md) — SOLID, thin controllers, YAGNI
- [`class-purpose-and-module-map`](skills/class-purpose-and-module-map/SKILL.md) — module ownership map ([full catalog](../docs/00-architecture/modules.md))
- [`code-style-and-conventions`](skills/code-style-and-conventions/SKILL.md) — Pint, TypeScript, naming
- [`review-and-risk-assessment`](skills/review-and-risk-assessment/SKILL.md) — P0/P1/P2 risk triage
- [`documentation-sync`](skills/documentation-sync/SKILL.md) — keep docs aligned with source
- [`multi-llm-plan-review`](skills/multi-llm-plan-review/SKILL.md) — plan audit workflow

### Safety (critical)

- [`xflickr-docker-testing`](skills/xflickr-docker-testing/SKILL.md) — test stack only; never dev
- [`docker-dev-stack-safety`](skills/docker-dev-stack-safety/SKILL.md) — zero contact on dev stack
- [`operator-dev-docker`](skills/operator-dev-docker/SKILL.md) — operator handoff for dev reload
- [`database-migration-safety`](skills/database-migration-safety/SKILL.md) — migrations on test stack only
- [`security-hardening`](skills/security-hardening/SKILL.md) — OAuth tokens, credentials, secrets

### Domain

- [`form-request-service-repository`](skills/form-request-service-repository/SKILL.md) — mandated request lifecycle
- [`crawler-pipeline-integrity`](skills/crawler-pipeline-integrity/SKILL.md) — manual crawl, rate limits
- [`transfer-pipeline-safety`](skills/transfer-pipeline-safety/SKILL.md) — download/upload dedup
- [`storage-driver-safety`](skills/storage-driver-safety/SKILL.md) — Google, OneDrive, R2
- [`queue-horizon-operations`](skills/queue-horizon-operations/SKILL.md) — Horizon queues
- [`api-response-standards`](skills/api-response-standards/SKILL.md) — `/api/*` JSON shapes
- [`react-inertia-frontend`](skills/react-inertia-frontend/SKILL.md) — UI component patterns

### Operations

- [`testing-and-quality-gates`](skills/testing-and-quality-gates/SKILL.md) — `bash scripts/test.sh gate`; factories + Faker test data
- [`release-and-deploy-flow`](skills/release-and-deploy-flow/SKILL.md) — dev reload, CHANGELOG

## End-to-end workflow (non-trivial features)

| Step | Agent | Artifact |
|---|---|---|
| 1. Plan | Cursor | `ai/plans/plan.md` |
| 2. Audit plan | Grok + Codex | `scripts/plan-review.sh` |
| 3. Final plan | Cursor | `ai/plans/final_plan.md` → human approval |
| 4. Implement | Cursor | code changes |
| 5. Test gate | Cursor | `bash scripts/test.sh gate` |
| 6. Review diff | Copilot CLI | `scripts/implementation-review.sh` |
| 7. Docs sync | Cursor | docs + CHANGELOG |
| 8. Cleanup | Cursor | delete `ai/plans/*.md` |

Details: [AI development workflow](../docs/04-development/ai-development-workflow.md)

## Ephemeral plans

`ai/plans/` holds temporary plan files (gitignored except `.gitkeep`). Delete after commit.
