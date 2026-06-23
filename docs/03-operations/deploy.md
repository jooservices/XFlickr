# Deploy

Deploy code changes to the local Docker stack without running migrations.

## Script

```bash
./scripts/deploy.sh
```

The script:

1. Builds frontend assets (`npm run build`)
2. Clears Laravel config and view caches
3. Restarts `app`, `horizon`, and `scheduler` containers

**No database migrations are run.** Run migrations manually only when you intend to change schema:

```bash
# User-initiated only — not for agents on dev stack without explicit approval
docker compose exec app php artisan migrate
```

## Production deploy checklist

1. Pull latest code
2. `composer install --no-dev --optimize-autoloader`
3. `npm ci && npm run build`
4. `php artisan migrate --force` (when schema changed)
5. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
6. Restart Horizon and scheduler processes
7. Verify app and Horizon dashboard

## See also

- [Release and deploy flow](../../ai/skills/release-and-deploy-flow/SKILL.md)
