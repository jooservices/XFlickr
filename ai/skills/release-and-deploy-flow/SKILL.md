---
name: release-and-deploy-flow
description: Deploy script, CHANGELOG cuts, and production checklist.
---

# Skill: release-and-deploy-flow

## Purpose

Guide safe deployment and release documentation.

## Local deploy (no migrations)

```bash
./scripts/deploy.sh
```

Rebuilds assets, clears caches, restarts app/horizon/scheduler. **Does not migrate.**

## CHANGELOG

- Unreleased work: `CHANGELOG.md` `## [Unreleased]` section
- Release cut: move Unreleased to `## [X.Y.Z] - YYYY-MM-DD` with compare link

## Production checklist

1. `composer install --no-dev --optimize-autoloader`
2. `npm ci && npm run build`
3. `php artisan migrate --force` (when schema changed — user-initiated)
4. `php artisan config:cache && php artisan route:cache`
5. Restart Horizon and scheduler
6. Verify app + Horizon dashboard

## Rules

- Agents do not run production migrations without explicit user request.
- Never deploy with `APP_DEBUG=true` in production.
- Update `CHANGELOG.md` for every release.

## Related skills

- `documentation-sync`
- `docker-dev-stack-safety`
