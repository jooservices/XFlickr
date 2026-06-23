#!/usr/bin/env bash
# USER-INITIATED ONLY — drops the local dev MongoDB database (xflickr).
# Agents: NEVER run this unless the user explicitly asks to reset MongoDB.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if ! docker compose ps --format '{{.Service}}' 2>/dev/null | grep -qx 'mongodb'; then
    echo "ERROR: local mongodb service is not running. Start with: docker compose up -d mongodb app" >&2
    exit 1
fi

echo "==> Dropping MongoDB database: xflickr"
docker compose exec -T mongodb mongosh --quiet --eval 'db.getSiblingDB("xflickr").dropDatabase()'

echo "==> Rebuilding MongoDB indexes (laravel-config, laravel-events)"
docker compose exec -T app php artisan config-store:ensure-index --no-interaction
docker compose exec -T app php artisan events:install-indexes --no-interaction

echo "==> Clearing application cache (laravel-config is cached in MySQL when CACHE_STORE=database)"
docker compose exec -T app php artisan cache:clear --no-interaction
docker compose exec -T app php artisan config-store:refresh --no-interaction

echo "==> Clearing Laravel config cache"
docker compose exec -T app php artisan config:clear --no-interaction

echo "Done. MongoDB xflickr is empty and config cache cleared. Re-enter Flickr/storage app credentials in Settings."
