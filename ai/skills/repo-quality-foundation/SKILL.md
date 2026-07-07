---
name: repo-quality-foundation
description: Baseline quality, scope discipline, and pre-PR readiness for XFlickr.
---

# Skill: repo-quality-foundation

## Purpose

Keep XFlickr changes aligned with repository standards, docs, and non-destructive validation.

## When to use

Any non-trivial change, documentation sync, or pre-PR readiness check.

## Required context

`AGENTS.md`, `composer.json`, `package.json`, `docs/README.md`, `git status --short`.

## Rules

- Preserve runtime behavior unless the task explicitly requests code changes.
- Do not change dependencies without explicit scope.
- Never run dev stack commands — use `bash scripts/test.sh` only.
- Never commit as Cursor Agent; use `Viet Vu <jooservices@gmail.com>`.
- Feature work requires `bash scripts/test.sh gate` green before PR.
- Non-trivial features follow `docs/04-development/ai-development-workflow.md`.
- Minimize scope — focused diffs only.

## Steps

1. Inspect repo state and changed files.
2. Identify affected docs and skills.
3. Run `bash scripts/test.sh gate` (and `npm run typecheck` if frontend changed).
4. Run `composer instructions:verify`.
5. Report skipped checks with reason.

## Related skills

- `architecture-and-design-principles`
- `xflickr-docker-testing`
- `operator-dev-docker`
- `testing-and-quality-gates`
- `documentation-sync`
- `review-and-risk-assessment`
