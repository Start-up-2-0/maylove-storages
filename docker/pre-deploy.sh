#!/bin/sh
set -e
# Pre-deploy roda sem volume montado — apenas migrations de banco.
exec /usr/local/bin/migrate-db.sh
