#!/usr/bin/env bash
# Deploy code changes to the local Docker stack without running migrations.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "==> Building frontend assets"
if command -v npm >/dev/null 2>&1; then
    npm run build
    rm -f public/hot
else
    docker compose exec -T app npm run build
    docker compose exec -T app rm -f public/hot
fi

echo "==> Clearing Laravel caches (no migrations)"
docker compose exec -T app php artisan config:clear --no-interaction
docker compose exec -T app php artisan view:clear --no-interaction

echo "==> Restarting app, Horizon, and scheduler"
docker compose restart app horizon scheduler

echo
echo "Deployed. No database migrations were run."
echo "  App:     http://localhost:8082"
echo "  Horizon: http://localhost:8082/horizon"
