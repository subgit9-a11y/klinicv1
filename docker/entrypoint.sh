#!/bin/sh
set -e

# Run as www-data for storage perms
cd /var/www/html

echo "Running migrations..."
php artisan migrate --force

echo "Optimizing autoloader & config..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Link storage if not already linked
if [ ! -L public/storage ]; then
    php artisan storage:link
fi

echo "Starting supervisor (nginx + php-fpm + scheduler + queue)..."
exec "$@"
