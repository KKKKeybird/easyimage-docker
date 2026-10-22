#!/bin/sh
set -eu
case "$PUID:$PGID" in *[!0-9:]*|:*|*:) echo 'PUID and PGID must be numeric' >&2; exit 1;; esac
if [ "$(id -g www-data)" != "$PGID" ]; then groupmod -o -g "$PGID" www-data; fi
if [ "$(id -u www-data)" != "$PUID" ]; then usermod -o -u "$PUID" www-data; fi
for directory in config i; do
    mkdir -p "/app/web/$directory"
    if [ -z "$(ls -A "/app/web/$directory")" ]; then
        cp -a "/opt/easyimage-defaults/$directory/." "/app/web/$directory/"
        chown -R www-data:www-data "/app/web/$directory"
    fi
done
mkdir -p /app/web/admin/logs /app/web/i/cache /run/php
chown www-data:www-data /app/web/config /app/web/i /app/web/i/cache /app/web/admin/logs
if [ "$DEBUG" = true ]; then
    printf 'display_errors=On\n' > /usr/local/etc/php/conf.d/zzz-debug.ini
fi
exec "$@"
