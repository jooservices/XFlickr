# Native PHP installation

Run XFlickr without Docker when you manage MySQL, Redis, and MongoDB yourself.

## Prerequisites

- PHP 8.5+ with extensions: pdo_mysql, redis, mongodb, intl, mbstring, xml, curl, zip
- Composer 2
- Node.js 20.19+ or 22.12+
- MySQL 8, Redis 7, MongoDB 7

## Steps

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Configure `.env` for your local MySQL, Redis, and MongoDB hosts (defaults assume Docker service names).

```bash
php artisan migrate
php artisan config-store:ensure-index
php artisan events:install-indexes
npm run build
```

## Run processes

```bash
php artisan serve                    # http://127.0.0.1:8000
php artisan horizon                  # queue worker
php artisan schedule:work            # or cron: * * * * * php artisan schedule:run
```

Set `APP_URL` and `FLICKR_CALLBACK_URL` to match your public URL.

## Next step

[First run](first-run.md)
