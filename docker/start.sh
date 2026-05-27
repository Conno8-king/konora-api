#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chmod -R ug+rwX storage bootstrap/cache || true

if [ "${DB_CONNECTION:-}" = "sqlite" ]; then
    DB_FILE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    mkdir -p "$(dirname "$DB_FILE")"
    [ -f "$DB_FILE" ] || touch "$DB_FILE"
fi

php artisan config:clear >/dev/null 2>&1 || true
php artisan route:clear >/dev/null 2>&1 || true
php artisan view:clear >/dev/null 2>&1 || true

if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force || true
fi

if [ "${RUN_STORAGE_LINK:-true}" = "true" ]; then
    php artisan storage:link || true
fi

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
