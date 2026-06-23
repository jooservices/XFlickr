---
name: documentation-sync
description: Keep docs, README, AGENTS, skills, and CHANGELOG aligned with source.
---

# Skill: documentation-sync

## Purpose

Prevent documentation drift after code changes.

## When to update

| Change type | Update |
|---|---|
| User-visible UI/behavior | `docs/02-user-guide/`, `CHANGELOG.md` `[Unreleased]` |
| Install/ports | `docs/01-getting-started/`, `README.md` |
| Architecture/layers | `docs/00-architecture/` |
| Agent rules | `AGENTS.md`, relevant `ai/skills/` |
| New dangerous commands | `docs/05-maintenance/known-dangerous-commands.md` |
| Doc initiative itself | `docs/05-maintenance/02-docs-changelog.md` |

## Rules

- Do not update historical files in `docs/05-maintenance/archive/`.
- README stays concise — deep content lives in `docs/`.
- After skill changes, run `composer instructions:verify`.
- Product name: **XFlickr**; package: **jooservices/xflickr**.

## Steps

1. List user-visible changes in the diff.
2. Update affected doc files.
3. Add `CHANGELOG.md` entry under `[Unreleased]`.
4. Verify links in `docs/README.md` and `AGENTS.md`.

## Related skills

- `repo-quality-foundation`
- `release-and-deploy-flow`
