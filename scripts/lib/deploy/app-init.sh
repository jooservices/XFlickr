#!/usr/bin/env bash
# First-boot Laravel setup shared by Docker entrypoint and host deploy.
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/artisan.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/common.sh"

deploy_app_wait_for_mysql() {
    local host="${DB_HOST:-mysql}"
    local port="${DB_PORT:-3306}"
    local user="${DB_USERNAME:-xflickr}"
    local pass="${DB_PASSWORD:-secret}"
    local tries=0

    until php -r "
        try {
            new PDO(
                'mysql:host=${host};port=${port}',
                '${user}',
                '${pass}',
                [PDO::ATTR_TIMEOUT => 2]
            );
            exit(0);
        } catch (Throwable \$e) {
            exit(1);
        }
    " >/dev/null 2>&1; do
        tries=$((tries + 1))
        if [[ "$tries" -ge 60 ]]; then
            echo "MySQL is not reachable at ${host}:${port}" >&2
            return 1
        fi
        sleep 1
    done
}

deploy_app_wait_for_redis() {
    local host="${REDIS_HOST:-redis}"
    local port="${REDIS_PORT:-6379}"
    local tries=0

    until php -r "
        \$socket = @fsockopen('${host}', (int) '${port}', \$errno, \$errstr, 2);
        if (\$socket === false) {
            exit(1);
        }
        fclose(\$socket);
        exit(0);
    " >/dev/null 2>&1; do
        tries=$((tries + 1))
        if [[ "$tries" -ge 60 ]]; then
            echo "Redis is not reachable at ${host}:${port}" >&2
            return 1
        fi
        sleep 1
    done
}

deploy_app_wait_for_mongodb() {
    local uri="${MONGODB_URI:-mongodb://mongodb:27017}"
    local tries=0

    until php -r "
        try {
            \$manager = new MongoDB\Driver\Manager('${uri}', ['serverSelectionTimeoutMS' => 2000]);
            \$manager->executeCommand('admin', new MongoDB\Driver\Command(['ping' => 1]));
            exit(0);
        } catch (Throwable \$e) {
            exit(1);
        }
    " >/dev/null 2>&1; do
        tries=$((tries + 1))
        if [[ "$tries" -ge 60 ]]; then
            echo "MongoDB is not reachable at ${uri}" >&2
            return 1
        fi
        sleep 1
    done
}

deploy_app_wait_for_services() {
    deploy_app_wait_for_mysql || return 1
    deploy_app_wait_for_redis || return 1
    deploy_app_wait_for_mongodb || return 1
}

deploy_app_ensure_app_key() {
    local root env_file app_key_line
    root="$(xf_script_root)"
    env_file="${root}/.env"
    app_key_line="$(grep -E '^APP_KEY=' "$env_file" 2>/dev/null | head -1 || true)"

    if [[ -z "$app_key_line" || "$app_key_line" == "APP_KEY=" ]]; then
        deploy_artisan_run key:generate --force --no-interaction
    fi
}

deploy_app_first_boot() {
    if [[ -z "${ADMIN_PASSWORD:-}" ]]; then
        echo "ERROR: ADMIN_PASSWORD is required in production (.env)." >&2
        return 1
    fi

    deploy_app_ensure_app_key || return 1
    deploy_artisan_run db:seed --class=Database\\Seeders\\AdminUserSeeder --force --no-interaction
    deploy_artisan_run config-store:ensure-index --no-interaction
    deploy_artisan_run events:install-indexes --no-interaction
}
