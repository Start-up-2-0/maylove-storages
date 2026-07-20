#!/bin/sh
set -e

if [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist
fi

/usr/local/bin/storage-bootstrap.sh

exec "$@"
