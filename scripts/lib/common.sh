#!/usr/bin/env bash
# Shared helpers for XFlickr operator and test scripts.
set -u
set -o pipefail

xf_script_root() {
    cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd
}

xf_load_env_file() {
    local file="$1"
    if [[ -f "$file" ]]; then
        set -a
        # shellcheck disable=SC1090
        source "$file"
        set +a
    fi
}

xf_load_root_env() {
    local root="$1"
    xf_load_env_file "${root}/.env"
}

xf_load_test_env() {
    local root="$1"
    if [[ -f "${root}/.env.test" ]]; then
        xf_load_env_file "${root}/.env.test"
    else
        xf_load_env_file "${root}/.env.test.example"
    fi
}

xf_app_port() {
    echo "${APP_HOST_PORT:-8082}"
}

xf_frontend_port() {
    echo "${FRONTEND_HOST_PORT:-5174}"
}

xf_wait_for_app() {
    local port
    port="$(xf_app_port)"
    local tries=0

    until curl -fsS "http://127.0.0.1:${port}/login" >/dev/null 2>&1; do
        tries=$((tries + 1))
        if [[ $tries -ge 60 ]]; then
            echo "App did not become ready on http://localhost:${port}" >&2
            return 1
        fi
        sleep 2
    done
}

xf_wait_for_frontend() {
    local port="${1:-$(xf_frontend_port)}"
    local tries=0

    until curl -fsS "http://127.0.0.1:${port}/@vite/client" >/dev/null 2>&1 \
        || curl -fsS "http://127.0.0.1:${port}/" >/dev/null 2>&1; do
        tries=$((tries + 1))
        if [[ $tries -ge 60 ]]; then
            echo "Frontend did not become ready on port ${port}" >&2
            return 1
        fi
        sleep 2
    done
}

xf_print_urls() {
    local app_port frontend_port
    app_port="$(xf_app_port)"
    frontend_port="$(xf_frontend_port)"

    echo
    echo "XFlickr is running:"
    echo "  App:      http://localhost:${app_port}"
    echo "  Login:    http://localhost:${app_port}/login"
    echo "  Horizon:  http://localhost:${app_port}/horizon"
    echo "  Settings: http://localhost:${app_port}/settings"
    echo "  Vite HMR: http://localhost:${frontend_port}"
    echo
    echo "  MySQL:    localhost:${MYSQL_HOST_PORT:-3308} (user ${MYSQL_USER:-xflickr} / db ${MYSQL_DATABASE:-xflickr})"
    echo "  Redis:    localhost:${REDIS_HOST_PORT:-6381}"
    echo "  MongoDB:  localhost:${MONGODB_HOST_PORT:-27019} (database xflickr)"
    echo
    echo "Tests (AI/CI only): bash scripts/test.sh gate"
    echo "NEVER on dev stack: docker exec xflickr-dev-*"
    echo
    echo "Admin: admin@local — password from container logs on first boot, or ADMIN_PASSWORD in .env"
    echo "Change password: docker exec xflickr-dev-app-1 php artisan xflickr:auth:reset-password admin@local"
}
