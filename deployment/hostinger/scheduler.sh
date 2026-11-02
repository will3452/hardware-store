#!/bin/sh
# Install beside the private laravel/ directory, outside public_html/.
set -eu
umask 077
cd "$(dirname "$0")/laravel"
exec /usr/bin/php artisan schedule:run >> storage/logs/scheduler.log 2>&1
