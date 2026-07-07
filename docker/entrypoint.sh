#!/bin/sh
set -e

if [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist
fi

mkdir -p "${STORAGE_ROOT:-/var/maylove/storage}"

php bin/console app:seed-music-library --no-interaction 2>/dev/null || true

exec "$@"
