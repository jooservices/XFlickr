# Contributing

Thank you for contributing to XFlickr.

Read [`AGENTS.md`](AGENTS.md) before making changes — especially the **absolute Docker database safety rule** (no tests or migrations on the local dev stack).

## Detailed guide

See [Contributing (full guide)](docs/04-development/07-contributing.md) for branch policy, pull requests, quality gates, and commit authorship rules.

## Quick checklist

1. Read `AGENTS.md` and relevant skills in [`ai/skills/`](ai/skills/).
2. Use the isolated test stack only: `composer test:docker`.
3. Run `npm run typecheck` when changing frontend code.
4. Update docs and `CHANGELOG.md` `[Unreleased]` for user-visible changes.
5. Run `composer instructions:verify` before opening a PR.

## Non-trivial features

Follow [AI Development Workflow](docs/04-development/ai-development-workflow.md) and the `multi-llm-plan-review` skill.

## Community

- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Security policy](SECURITY.md)
- [Changelog](CHANGELOG.md)
