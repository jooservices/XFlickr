---
name: multi-llm-plan-review
description: Plan-before-code workflow with Grok audit, Codex debate, and Copilot review.
---

# Skill: multi-llm-plan-review

## Purpose

Orchestrate multi-LLM review for non-trivial XFlickr features.

## Tier routing

| Tier | Examples | Grok + Codex | Copilot CLI |
|---|---|---|---|
| Small | Typo, one-line fix | Skip | Skip |
| Medium | New endpoint, service method | Yes | After green gate |
| Large | Cross-module, crawl, transfer, storage | Yes | After green gate |

## Workflow

1. Cursor writes `ai/plans/plan.md` (scope, files, tests, risks)
2. `bash scripts/plan-review.sh` — Grok audit + Codex debate
3. Cursor writes `ai/plans/final_plan.md` → **human approval**
4. Implement per final plan
5. `composer test:docker` — mandatory gate
6. `bash scripts/implementation-review.sh` — Copilot diff review (medium+)
7. Documentation sync → CHANGELOG
8. Delete `ai/plans/*.md`

## Rules

- Do not implement before `final_plan.md` is approved (large/medium tiers).
- Grok and Codex are read-only auditors — Cursor implements.
- CLI tools optional: document skip reason if `grok`/`codex` not on PATH.

## References

- `docs/04-development/ai-development-workflow.md`
- `.cursor/prompts/plan-audit-grok.md`
- `.cursor/prompts/plan-debate-codex.md`

## Related skills

- `repo-quality-foundation`
- `documentation-sync`
