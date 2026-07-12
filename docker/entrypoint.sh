#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

wait_for_mysql() {
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
        if [ "$tries" -ge 60 ]; then
            echo "MySQL is not reachable at ${host}:${port}" >&2
            exit 1
        fi
        sleep 1
    done
}

wait_for_redis() {
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
        if [ "$tries" -ge 60 ]; then
            echo "Redis is not reachable at ${host}:${port}" >&2
            exit 1
        fi
        sleep 1
    done
}

wait_for_mongodb() {
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
        if [ "$tries" -ge 60 ]; then
            echo "MongoDB is not reachable at ${uri}" >&2
            exit 1
        fi
        sleep 1
    done
}

if [ ! -f vendor/autoload.php ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist
fi

wait_for_mysql
wait_for_redis
wait_for_mongodb

# Only generate when .env has no key. Do not use the shell APP_KEY env var:
# docker-compose does not pass it by default, and key:generate --force would
# rotate the key on every container start and break encrypted OAuth tokens.
app_key_line="$(grep -E '^APP_KEY=' .env 2>/dev/null | head -1 || true)"
if [ -z "$app_key_line" ] || [ "$app_key_line" = "APP_KEY=" ]; then
    php artisan key:generate --force --no-interaction
fi

php artisan config:clear --no-interaction

ensure_admin_password() {
    if [ -n "${ADMIN_PASSWORD:-}" ]; then
        return 0
    fi

    ADMIN_PASSWORD="$(php -r 'echo bin2hex(random_bytes(12));')"
    export ADMIN_PASSWORD

    printf '%s\n' "$ADMIN_PASSWORD" > /tmp/xflickr-admin-password
    chmod 600 /tmp/xflickr-admin-password

    echo "============================================================"
    echo "XFlickr admin account (save this password — shown once):"
    echo "  email:    ${ADMIN_EMAIL:-admin@local}"
    echo "  password: ${ADMIN_PASSWORD}"
    echo "Also written to /tmp/xflickr-admin-password in this container."
    echo "Set ADMIN_PASSWORD in .env to use a fixed password instead."
    echo "============================================================"
}

# Only the web app container runs migrations (horizon/scheduler share DB but not /tmp locks).
if [ "${1:-}" = "php" ] && [ "${2:-}" = "artisan" ] && [ "${3:-}" = "serve" ]; then
    ensure_admin_password
    php artisan migrate --force --no-interaction
    php artisan db:seed --class=Modules\\Auth\\Database\\Seeders\\AdminUserSeeder --force --no-interaction
    php artisan config-store:ensure-index --no-interaction
    php artisan events:install-indexes --no-interaction
fi

if [ "${1:-}" = "php" ] && [ "${2:-}" = "artisan" ] && [ "${3:-}" = "test" ]; then
    echo "ERROR: Do not run tests in the local dev stack (wipes MySQL dev data via RefreshDatabase)." >&2
    echo "Use: bash scripts/test.sh gate:test" >&2
    echo "Note: docker exec xflickr-dev-* also bypasses this guard — never exec DB commands on the dev stack." >&2
    exit 1
fi

if [ "${1:-}" = "php" ] && [ "${2:-}" = "artisan" ] && { [ "${3:-}" = "migrate:fresh" ] || [ "${3:-}" = "migrate:refresh" ] || [ "${3:-}" = "migrate:reset" ] || [ "${3:-}" = "db:wipe" ] || [ "${3:-}" = "db:seed" ] || [ "${3:-}" = "migrate" ]; }; then
    echo "ERROR: Database command blocked on local dev stack (agents: use docker-compose.test.yml)." >&2
    echo "Command: php artisan ${3:-}" >&2
    echo "This can modify or destroy dev MySQL/MongoDB data." >&2
    exit 1
fi

exec "$@"
