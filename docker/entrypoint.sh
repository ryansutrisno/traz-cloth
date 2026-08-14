#!/bin/sh

set -eu

cd /var/www/html

php artisan migrate --force --no-interaction

if [ "${APP_ENV:-}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
fi

exec "$@"
