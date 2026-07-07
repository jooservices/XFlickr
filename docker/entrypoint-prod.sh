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
    echo "Installing Composer dependencies (production)..."
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
fi

wait_for_mysql
wait_for_redis
wait_for_mongodb

app_key_line="$(grep -E '^APP_KEY=' .env 2>/dev/null | head -1 || true)"
if [ -z "$app_key_line" ] || [ "$app_key_line" = "APP_KEY=" ]; then
    php artisan key:generate --force --no-interaction
fi

php artisan config:clear --no-interaction

if [ "${1:-}" = "php" ] && [ "${2:-}" = "artisan" ] && [ "${3:-}" = "serve" ]; then
    if [ -z "${ADMIN_PASSWORD:-}" ]; then
        echo "ERROR: ADMIN_PASSWORD is required in production (.env)." >&2
        exit 1
    fi

    if [ ! -d public/build ] || [ -z "$(ls -A public/build 2>/dev/null || true)" ]; then
        echo "Building frontend assets..."
        npm ci --no-audit --no-fund
        npm run build
    fi

    php artisan migrate --force --no-interaction
    php artisan db:seed --class=Database\\Seeders\\AdminUserSeeder --force --no-interaction
    php artisan config-store:ensure-index --no-interaction
    php artisan events:install-indexes --no-interaction
    php artisan config:cache --no-interaction
    php artisan route:cache --no-interaction
    php artisan view:cache --no-interaction
fi

exec "$@"
