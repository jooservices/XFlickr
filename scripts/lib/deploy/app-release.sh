#!/usr/bin/env bash
# Shared Laravel release steps for production deploy (Docker and host).
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/artisan.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/compose-prod.sh"

deploy_app_migrate() {
    echo "==> Running migrations (additive only — no data wipe)"
    deploy_artisan_run migrate --force --no-interaction
}

deploy_app_optimize_refresh() {
    echo "==> Clearing Laravel caches"
    deploy_artisan_bash_lc 'set -e
php artisan config:clear --no-interaction
php artisan route:clear --no-interaction
php artisan view:clear --no-interaction
php artisan cache:clear --no-interaction
php artisan optimize:clear --no-interaction'

    echo "==> Rebuilding Laravel caches"
    deploy_artisan_bash_lc 'set -e
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction'
}

deploy_app_graceful_worker_shutdown() {
    local mode running=0
    mode="$(deploy_artisan_mode)"

    if [[ "$mode" == "docker" ]]; then
        running="$(xf_prod_compose ps --status running -q 2>/dev/null | wc -l | tr -d ' ')"
        if [[ "${running:-0}" -eq 0 ]]; then
            return 0
        fi
    elif [[ "$mode" == "host" ]]; then
        if ! pgrep -f "artisan horizon" >/dev/null 2>&1; then
            return 0
        fi
    fi

    echo "==> Gracefully stopping Horizon workers (horizon:terminate)"
    deploy_artisan horizon:terminate --wait 2>/dev/null \
        || deploy_artisan horizon:terminate 2>/dev/null \
        || true
}
