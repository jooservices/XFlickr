#!/usr/bin/env bash
# Operator reload after code changes (no DB impact).
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/compose-dev.sh"

dev_reload_stack() {
    echo "==> Syncing frontend dependencies in app container"
    xf_dev_compose exec -T app sh scripts/docker-npm-sync.sh

    echo "==> Building frontend assets in app container"
    xf_dev_compose exec -T app npm run build
    xf_dev_compose exec -T app rm -f public/hot

    echo "==> Clearing Laravel caches (no migrations)"
    xf_dev_compose exec -T app php artisan config:clear --no-interaction
    xf_dev_compose exec -T app php artisan view:clear --no-interaction

    echo "==> Restarting app, Horizon, and scheduler"
    xf_dev_compose restart app horizon scheduler

    local port
    port="$(xf_app_port)"
    echo
    echo "Reloaded. No database migrations were run."
    echo "  App:     http://localhost:${port}"
    echo "  Horizon: http://localhost:${port}/horizon"
}
