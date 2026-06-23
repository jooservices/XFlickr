# Contributing (full guide)

## Before you start

1. Read [`AGENTS.md`](../../AGENTS.md) — **especially the Docker database safety rule**.
2. Read [Coding standards](coding-standards.md) and [Testing](testing.md).
3. For non-trivial features, follow [AI development workflow](ai-development-workflow.md).

## Git commit authorship

All commits must use `Viet Vu <jooservices@gmail.com>` as both author and committer.

- Never commit as `Cursor Agent`, `cursoragent@cursor.com`, or any AI identity.
- Do not add `Co-authored-by` trailers for Cursor, Copilot, Gemini, Grok, or Codex.
- Do not change git config to bypass this rule.

## Branch policy

- One PR per feature or fix.
- Short branch names: `feature/<name>`, `fix/<name>`, `docs/<name>`, `chore/<name>`.
- Keep branches up to date before review.

## Quality gates

Before every PR:

```bash
composer test:docker
npm run typecheck          # when frontend changed
composer instructions:verify
```

**Never** run `docker compose exec app php artisan test` on the dev stack.

## Pull requests

See [Pull request standard](pull-request-standard.md).

## Documentation

Update docs when you change:

- User-visible behavior → `docs/02-user-guide/` and `CHANGELOG.md` `[Unreleased]`
- Architecture or layering → `docs/00-architecture/`
- Agent rules or skills → `AGENTS.md`, `ai/skills/`, [AI skills maintenance](ai-skills.md), run `composer instructions:verify`

## Code style

- PHP: `vendor/bin/pint` before commit
- TypeScript: `npm run typecheck`

## Questions

Open a GitHub issue for bugs or feature requests. For security issues, see [SECURITY.md](../../SECURITY.md).
