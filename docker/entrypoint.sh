#!/bin/sh
set -e

# The host tells the container which port to listen on. Apache listens on 80 unless told otherwise.
PORT="${PORT:-10000}"
sed -ri "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# The settings come from environment variables, which only exist now, not when the image was built.
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec apache2-foreground
