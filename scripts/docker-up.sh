#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [ ! -f .env ]; then
    cp .env.example .env
    echo "Created .env from .env.example"
fi

chmod +x docker/entrypoint.sh docker/test-entrypoint.sh

echo "==> Starting local Docker stack (MySQL, Redis, MongoDB, app, Horizon, scheduler)"
docker compose up -d --build --wait mysql redis mongodb app horizon scheduler

echo "==> Installing PHP dependencies"
docker compose exec -T app composer install --no-interaction --prefer-dist

echo "==> Installing frontend dependencies and building assets"
if command -v npm >/dev/null 2>&1; then
    npm ci
    npm run build
    rm -f public/hot
else
    docker compose exec -T app npm ci
    docker compose exec -T app npm run build
    docker compose exec -T app rm -f public/hot
fi

echo "==> Running migrations and MongoDB bootstrap"
docker compose exec -T app php artisan migrate --force --no-interaction
docker compose exec -T app php artisan config-store:ensure-index --no-interaction
docker compose exec -T app php artisan events:install-indexes --no-interaction

echo
echo "XFlickr is running:"
echo "  App:      http://localhost:8082"
echo "  Horizon:  http://localhost:8082/horizon"
echo "  MySQL:    localhost:3308 (user xflickr / secret / db xflickr)"
echo "  Redis:    localhost:6381"
echo "  MongoDB:  localhost:27019 (database xflickr; use mongodb://mongodb:27017 inside containers)"
echo
echo "Optional Vite dev server: docker compose up -d vite"
echo "Run tests (isolated stack ONLY): composer test:docker  OR  ./scripts/test-docker.sh"
echo "NEVER: docker compose exec app php artisan test  (wipes dev MySQL)"
echo "Set Flickr app credentials in Settings, then connect an account."
