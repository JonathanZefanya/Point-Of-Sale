#!/bin/sh
set -e
cd /var/www/html


composer install --no-interaction --prefer-dist
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
[ -L public/storage ] || php artisan storage:link >/dev/null 2>&1 || true

exec "$@"
