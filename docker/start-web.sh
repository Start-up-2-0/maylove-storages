#!/bin/sh
set -e

PORT="${PORT:-8080}"
echo "[maylove-storages] starting PHP server on 0.0.0.0:${PORT}"

php -S "0.0.0.0:${PORT}" -t public public/index.php &
SERVER_PID=$!

/usr/local/bin/migrate.sh || echo "[maylove-storages] WARN: migrations on start failed (see logs)"

php bin/console app:seed-music-library --no-interaction 2>/dev/null \
    || echo "[maylove-storages] WARN: music library seed skipped (see logs)"

wait "$SERVER_PID"
