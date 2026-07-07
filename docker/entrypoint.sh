#!/bin/sh
set -e

if [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist
fi

mkdir -p "${STORAGE_ROOT:-/var/maylove/storage}"

exec "$@"
