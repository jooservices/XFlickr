#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

git config --global --add safe.directory /var/www/html 2>/dev/null || true

# shellcheck disable=SC1091
source /var/www/html/scripts/lib/deploy/app-init.sh

if [ ! -f vendor/autoload.php ]; then
    echo "Installing Composer dependencies (production)..."
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
fi

if [ ! -f vendor/jooservices/xflickr-crawler/src/XFlickrCrawlerServiceProvider.php ]; then
    echo "ERROR: jooservices/xflickr-crawler is missing from vendor/." >&2
    echo "Run on the host: bash scripts/deploy.sh finish" >&2
    exit 1
fi

deploy_app_wait_for_services

app_key_line="$(grep -E '^APP_KEY=' .env 2>/dev/null | head -1 || true)"
if [ -z "$app_key_line" ] || [ "$app_key_line" = "APP_KEY=" ]; then
    php artisan key:generate --force --no-interaction
fi

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
fi

exec "$@"
