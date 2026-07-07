# Native PHP installation

For production on Ubuntu 22.04 without Docker, use the deploy wizard with **host** target:

```bash
bash scripts/deploy.sh install
```

Choose **Host** when prompted. The script installs PHP 8.5+, Composer, Node, nginx, and supervisor when missing (Ubuntu 22.04), runs the same service wizard as Docker deploy, and verifies health before reporting success.

## Manual development setup

For local development without Docker, install prerequisites yourself:

- PHP 8.5+ with extensions: pdo_mysql, redis, mongodb, intl, mbstring, xml, curl, zip, bcmath, pcntl
- Composer 2
- Node.js 20.19+ or 22.12+
- MySQL 8, Redis 7, MongoDB 7

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Configure `.env` for your local MySQL, Redis, and MongoDB hosts.

```bash
php artisan migrate
php artisan config-store:ensure-index
php artisan events:install-indexes
npm run build
```

## Run processes (development)

```bash
php artisan serve
php artisan horizon
php artisan schedule:work
```

Set `APP_URL` and `FLICKR_CALLBACK_URL` to match your public URL.

## Next step

[First run](first-run.md)
