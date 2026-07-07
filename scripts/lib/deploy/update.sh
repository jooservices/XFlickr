#!/usr/bin/env bash
# Production stack update (git pull + rebuild + migrate).
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/compose-prod.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/verify.sh"

deploy_update_stack() {
    local root replicas
    root="$(xf_script_root)"
    replicas="$(xf_prod_horizon_replicas)"

    if [[ -d "${root}/.git" ]]; then
        echo "==> git pull"
        git -C "$root" pull --ff-only
    fi

    echo "==> Rebuilding production images"
    xf_prod_compose build app

    echo "==> Installing dependencies and building frontend assets"
    xf_prod_compose run --rm --no-deps app bash -lc \
        'composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader && npm ci --no-audit --no-fund && npm run build'

    echo "==> Running migrations (additive only — no data wipe)"
    xf_prod_compose run --rm --no-deps app php artisan migrate --force --no-interaction

    echo "==> Refreshing Laravel caches"
    xf_prod_compose run --rm --no-deps app bash -lc \
        'php artisan config:cache --no-interaction && php artisan route:cache --no-interaction && php artisan view:cache --no-interaction'

    echo "==> Restarting containers (rolling — data volumes preserved)"
    xf_prod_compose up -d --build --scale "horizon=${replicas}" app horizon scheduler nginx

    if ! deploy_verify_stack; then
        return 1
    fi

    echo "Production update complete."
    xf_prod_print_urls
}
