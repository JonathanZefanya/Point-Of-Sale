#!/bin/sh
set -e

# Volume storage bisa masih kosong saat pertama dipasang → pastikan struktur & pemiliknya benar
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache

# Link public/storage → storage/app/public (gambar produk). Tidak ikut di image (.dockerignore).
[ -L public/storage ] || php artisan storage:link
php artisan storage:link --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
exec "$@"
