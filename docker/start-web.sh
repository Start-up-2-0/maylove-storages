#!/bin/sh
set -e

PORT="${PORT:-8080}"
echo "[maylove-storages] starting PHP server on 0.0.0.0:${PORT}"

php -S "0.0.0.0:${PORT}" -t public public/index.php &
SERVER_PID=$!

/usr/local/bin/migrate.sh || echo "[maylove-storages] WARN: migrations on start failed (see logs)"

wait "$SERVER_PID"
