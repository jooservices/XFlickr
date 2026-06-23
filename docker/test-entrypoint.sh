#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

if [ ! -f vendor/autoload.php ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist
fi

if [ "${APP_ENV:-}" != "testing" ]; then
    echo "Test container requires APP_ENV=testing (got: ${APP_ENV:-unset})." >&2
    exit 1
fi

if [ "${DB_CONNECTION:-}" = "mysql" ] && [ "${DB_DATABASE:-}" = "xflickr" ]; then
    echo "Refusing to run tests against development MySQL database \"xflickr\"." >&2
    echo "Use: docker compose -f docker-compose.test.yml run --rm test" >&2
    exit 1
fi

if [ "${DB_CONNECTION:-}" != "sqlite" ] || [ "${DB_DATABASE:-}" != ":memory:" ]; then
    echo "Test container requires DB_CONNECTION=sqlite and DB_DATABASE=:memory:." >&2
    echo "Got DB_CONNECTION=${DB_CONNECTION:-unset} DB_DATABASE=${DB_DATABASE:-unset}" >&2
    exit 1
fi

php artisan config:clear --no-interaction

exec "$@"
