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
- Never run database commands on the local dev Docker stack.
- Never commit as Cursor Agent; use `Viet Vu <jooservices@gmail.com>`.
- Feature work requires `composer test:docker` green before PR.
- Non-trivial features follow `docs/04-development/ai-development-workflow.md`.
- Minimize scope — focused diffs only.

## Steps

1. Inspect repo state and changed files.
2. Identify affected docs and skills.
3. Run `composer test:docker` (and `npm run typecheck` if frontend changed).
4. Run `composer instructions:verify`.
5. Report skipped checks with reason.

## Related skills

- `architecture-and-design-principles`
- `xflickr-docker-testing`
- `testing-and-quality-gates`
- `documentation-sync`
- `review-and-risk-assessment`
