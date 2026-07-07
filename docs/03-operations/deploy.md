# Deploy

XFlickr has three Docker stacks. Use the script that matches your environment.

| Stack | Reload / deploy | Migrations |
|---|---|---|
| **Dev** (local) | `bash scripts/dev.sh reload` | `bash scripts/dev.sh up` (migrate on start) |
| **Testing** (CI) | `bash scripts/test.sh gate` | Handled by test stack |
| **Production** (server) | `bash scripts/deploy.sh update` | Included in `update` and first `install` |

## Local dev reload

Rebuild assets and restart workers **without** running migrations:

```bash
bash scripts/dev.sh reload
```

This:

1. Builds frontend assets (`npm run build`)
2. Clears Laravel config and view caches
3. Restarts `app`, `horizon`, and `scheduler` containers

## Production

First install and ongoing updates:

```bash
bash scripts/deploy.sh install
bash scripts/deploy.sh update
```

See [Production deploy](production-deploy.md) for the full wizard, external database setup, and Horizon scaling.

## See also

- [Docker stacks](docker-stacks.md)
- [Production deploy](production-deploy.md)
- [Release and deploy flow](../../ai/skills/release-and-deploy-flow/SKILL.md)
