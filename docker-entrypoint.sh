#!/bin/sh
set -e
# storage/ holds the SQLite database, rate-limit salt and logs; it must be writable by Apache.
mkdir -p /var/www/html/storage/logs
chown -R www-data:www-data /var/www/html/storage
exec "$@"
