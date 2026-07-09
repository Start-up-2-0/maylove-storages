#!/bin/sh
set -e

PORT="${PORT:-8080}"
echo "[maylove-storages] running migrations before start..."
/usr/local/bin/migrate.sh

echo "[maylove-storages] starting PHP server on 0.0.0.0:${PORT}"
( php bin/console app:seed-music-library --no-interaction 2>/dev/null || true ) &

exec php -S "0.0.0.0:${PORT}" -t public public/router.php
