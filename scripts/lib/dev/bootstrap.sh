#!/usr/bin/env bash
# Dev stack bootstrap (migrate, indexes, deps). Operator only — not for AI agents.
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/compose-dev.sh"

ADMIN_SEEDER='Database\\Seeders\\AdminUserSeeder'

xf_dev_ensure_env() {
    local root="$1"

    if [[ ! -f "${root}/.env" ]]; then
        cp "${root}/.env.example" "${root}/.env"
        echo "Created .env from .env.example"
    fi

    chmod +x "${root}/docker/entrypoint.sh" "${root}/docker/test-entrypoint.sh" "${root}/scripts/docker-dev.sh"
}

xf_dev_core_services() {
    echo mysql redis mongodb app horizon scheduler frontend
}

xf_dev_start_stack() {
    local services
    services="$(xf_dev_core_services)"

    echo "==> Starting dev Docker stack (${services})"
    xf_dev_compose up -d --build --wait ${services}
    xf_dev_compose ps
}

xf_dev_prepare_deps() {
    echo "==> Installing PHP dependencies in app container"
    xf_dev_compose exec -T app composer install --no-interaction --prefer-dist

    echo "==> Installing frontend dependencies and building assets in app container"
    xf_dev_compose exec -T app npm ci
    xf_dev_compose exec -T app npm run build
    xf_dev_compose exec -T app rm -f public/hot
}

xf_dev_bootstrap_migrate() {
    echo "==> Running migrations and MongoDB bootstrap (migrate only)"
    xf_dev_compose exec -T app php artisan view:clear --no-interaction
    xf_dev_compose exec -T app php artisan migrate --force --no-interaction
    xf_dev_compose exec -T app php artisan config-store:ensure-index --no-interaction
    xf_dev_compose exec -T app php artisan events:install-indexes --no-interaction
}

xf_dev_bootstrap_seed() {
    echo "==> Seeding admin user"
    xf_dev_compose exec -T app php artisan db:seed --class="${ADMIN_SEEDER}" --force --no-interaction
}

xf_dev_bootstrap_refresh() {
    echo "==> Dev refresh (migrate:fresh + admin seed only — destructive)"
    xf_dev_prepare_deps
    xf_dev_compose exec -T app php artisan migrate:fresh --force --no-interaction \
        --seeder="${ADMIN_SEEDER}"
    xf_dev_compose exec -T app php artisan config-store:ensure-index --no-interaction
    xf_dev_compose exec -T app php artisan events:install-indexes --no-interaction
}

xf_dev_up() {
    local root
    root="$(xf_script_root)"

    xf_dev_ensure_env "$root"
    xf_dev_start_stack
    xf_dev_prepare_deps
    xf_dev_bootstrap_migrate
    xf_wait_for_app
    xf_wait_for_frontend
    xf_print_urls
}

xf_dev_seed() {
    local root
    root="$(xf_script_root)"

    xf_dev_ensure_env "$root"
    xf_dev_start_stack
    xf_dev_prepare_deps
    xf_dev_bootstrap_migrate
    xf_dev_bootstrap_seed
    xf_wait_for_app
    xf_wait_for_frontend
    xf_print_urls
}

xf_dev_quick() {
    local root
    root="$(xf_script_root)"

    xf_dev_ensure_env "$root"
    xf_dev_start_stack
    xf_wait_for_app
    xf_wait_for_frontend
    xf_print_urls
}
