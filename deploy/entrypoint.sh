#!/bin/sh
set -e
php artisan config:cache
php artisan view:cache
exec "$@"
