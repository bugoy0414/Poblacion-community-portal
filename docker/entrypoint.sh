#!/bin/sh
set -eu

if [ "$#" -gt 0 ]; then
    exec "$@"
fi

port="${PORT:-10000}"
sed -i "s/^Listen 10000$/Listen ${port}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:10000>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

php artisan config:cache
exec apache2-foreground
