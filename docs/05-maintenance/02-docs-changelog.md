# Documentation changelog

Record of significant documentation and AI governance changes (not package releases).

## 2026-07-13 (Flickr client factory backlog)

- Added [flickr-client-factory-layering.md](flickr-client-factory-layering.md) and backlog **N-16**: SDK → Crawler ClientFactory → peers; Flickr module stays account facade (not factory owner).
- Linked from `package-boundaries.md` Planned section and `BACKLOG.md`.

## 2026-07-12 (modules catalog)

- Added `docs/00-architecture/modules.md` — per-module scope, purpose, and features for all nine Laravel modules plus host `app/` leftovers.
- Linked from `docs/README.md`, `AGENTS.md`, `project-overview`, `package-boundaries`, `repository-structure`, `ai/README.md`, and expanded `class-purpose-and-module-map` / `documentation-sync` skills.

## 2026-07-11 (modules migration complete)

- Architecture docs (`application-standards`, `package-boundaries`, `repository-structure`) and `AGENTS.md` updated for nine `Modules/*` domains; thin `app/` host only.
- Frontend skill prefers `AppShell` + `PageShell` over `PageHeading`; crawl listeners register on module `EventServiceProvider`s.

## 2026-06-23 (architecture diagrams)

- Added `docs/00-architecture/architecture-diagrams.md` — Mermaid charts for end-to-end journey, system context, manual crawl, download/upload, storage browse, OAuth, Horizon queues, and data stores.
- Cross-linked from `project-overview.md`, `data-flow.md`, and `docs/README.md`.

## 2026-06-23

- Bootstrapped full documentation hub (`docs/README.md`, sections `00`–`05`).
- Added governance files: LICENSE, CHANGELOG, CONTRIBUTING, SECURITY, CODE_OF_CONDUCT.
- Expanded AGENTS.md, CLAUDE.md, and `ai/skills/` ecosystem.
- Added multi-LLM development workflow and adapter sync verification.
- Archived historical planning docs to `docs/05-maintenance/archive/`.
- Migrated `docker-safety.md` from `docs/development/` to `docs/05-maintenance/`.
- Relocated `audit.md` to `codebase-audit-2026-06.md` with resolved-status notes.

## 2026-06-23 (agent/AI docs audit)

- Linked `docs/04-development/ai-skills.md` from docs hub, setup, and contributing.
- Replaced duplicate `.cursor/skills/xflickr-docker-testing` body with canonical stub.
- Aligned `xflickr-docker-testing` and `react-inertia-frontend` skill section headings.
- Added YAML frontmatter to `.cursor/rules/ui-buttons.mdc`.
- Added missing `ai/plans/.gitkeep`.
- Expanded `composer instructions:verify` to check all 20 Copilot skill stubs.
- Refreshed `risks-legacy-and-gaps.md` and audit status table for resolved items.
