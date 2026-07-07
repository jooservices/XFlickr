#!/usr/bin/env bash
# USER-INITIATED ONLY — drops the local dev MongoDB database (xflickr).
# Agents: NEVER run this unless the user explicitly asks to reset MongoDB.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/compose-dev.sh"

if ! xf_dev_compose ps --format '{{.Service}}' 2>/dev/null | grep -qx 'mongodb'; then
    echo "ERROR: dev mongodb service is not running. Start with: bash scripts/dev.sh up" >&2
    exit 1
fi

echo "==> Dropping MongoDB database: xflickr"
xf_dev_compose exec -T mongodb mongosh --quiet --eval 'db.getSiblingDB("xflickr").dropDatabase()'

echo "==> Rebuilding MongoDB indexes (laravel-config, laravel-events)"
xf_dev_compose exec -T app php artisan config-store:ensure-index --no-interaction
xf_dev_compose exec -T app php artisan events:install-indexes --no-interaction

echo "==> Clearing application cache (laravel-config is cached in MySQL when CACHE_STORE=database)"
xf_dev_compose exec -T app php artisan cache:clear --no-interaction
xf_dev_compose exec -T app php artisan config-store:refresh --no-interaction

echo "==> Clearing Laravel config cache"
xf_dev_compose exec -T app php artisan config:clear --no-interaction

echo "Done. MongoDB xflickr is empty and config cache cleared. Re-enter Flickr/storage app credentials in Settings."
