# CLAUDE.md — XFlickr

Claude Code entry point. **All rules live in [`AGENTS.md`](AGENTS.md)** — read that file first.

## Claude-specific rules

- **Analysis and planning mode by default** for architecture questions — inspect source before proposing changes.
- Do not implement code during plan-only requests.
- For non-trivial features, follow [AI development workflow](docs/04-development/ai-development-workflow.md).
- Never run database commands on the local dev Docker stack (see `AGENTS.md` Docker policy).

## Quick links

| Resource | Path |
|---|---|
| Agent rules | [`AGENTS.md`](AGENTS.md) |
| Skills map | [`ai/README.md`](ai/README.md) |
| Documentation | [`docs/README.md`](docs/README.md) |
| Docker safety | [`docs/05-maintenance/docker-safety.md`](docs/05-maintenance/docker-safety.md) |

## Slash commands

See `.claude/commands/` for task-specific workflows:

- `quality-check` — run quality gates
- `docs-sync` — align docs with source
- `security-review` — security pass
- `package-change` — dependency or package work
- `release-readiness` — pre-release checklist
- `ci-triage` — investigate CI failures
- `schema-review` — migration review
