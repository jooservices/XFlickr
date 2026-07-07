---
name: release-and-deploy-flow
description: Deploy script, CHANGELOG cuts, and production checklist.
---

# Skill: release-and-deploy-flow

## Purpose

Guide safe deployment and release documentation.

## Local dev reload (no migrations)

```bash
bash scripts/dev.sh reload
```

Rebuilds assets in container, clears caches, restarts app/horizon/scheduler. **Does not migrate.**

## Production deploy

```bash
bash scripts/deploy.sh install   # first-time wizard + external DB setup
bash scripts/deploy.sh update    # git pull + rebuild + migrate + restart
```

See `docs/03-operations/production-deploy.md`.

## CHANGELOG

- Unreleased work: `CHANGELOG.md` `## [Unreleased]` section
- Release cut: move Unreleased to `## [X.Y.Z] - YYYY-MM-DD` with compare link

## Branch model and GitHub release

- Branches: `develop` (integration), `master` (production), `release/*` (stabilization)
- Tag `v*.*.*` on `master` triggers `.github/workflows/release.yml` (changelog + GitHub Release)
- Branch protection: see `docs/05-maintenance/github-branch-protection.md`
- Semantic PR titles enforced via `.github/workflows/semantic-pr.yml`

## Production checklist

1. `bash scripts/deploy.sh update` (or manual equivalent)
2. Verify app and Horizon dashboard
3. Confirm Flickr callback URL matches `APP_URL`

## Rules

- Agents do not run `scripts/deploy.sh` or `scripts/dev.sh`.
- Agents do not run production migrations without explicit user request.
- Never deploy with `APP_DEBUG=true` in production.
- Update `CHANGELOG.md` for every release.

## Related skills

- `documentation-sync`
- `docker-dev-stack-safety`
