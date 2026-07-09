#!/bin/sh
set -e

# Railway injeta RAILWAY_VOLUME_MOUNT_PATH quando um volume está anexado ao serviço.
if [ -n "${RAILWAY_VOLUME_MOUNT_PATH:-}" ]; then
  if [ -z "${STORAGE_ROOT:-}" ]; then
    export STORAGE_ROOT="${RAILWAY_VOLUME_MOUNT_PATH}"
  elif [ "${STORAGE_ROOT}" != "${RAILWAY_VOLUME_MOUNT_PATH}" ]; then
    echo "[maylove-storages] WARNING: STORAGE_ROOT (${STORAGE_ROOT}) difere do volume Railway (${RAILWAY_VOLUME_MOUNT_PATH}). Usando o volume."
    export STORAGE_ROOT="${RAILWAY_VOLUME_MOUNT_PATH}"
  fi
fi

if [ -z "${STORAGE_ROOT:-}" ]; then
  export STORAGE_ROOT="/var/maylove/storage"
fi

APP_ENV="$(printf '%s' "${APP_ENV:-}" | tr -d '"')"

# No Railway em produção, volume persistente é obrigatório — sem ele os arquivos somem a cada redeploy.
if [ "${APP_ENV}" = "prod" ] && [ -n "${RAILWAY_ENVIRONMENT:-}" ]; then
  if [ -z "${RAILWAY_VOLUME_MOUNT_PATH:-}" ]; then
    echo ""
    echo "ERROR: Nenhum volume persistente anexado ao serviço maylove-storages no Railway."
    echo ""
    echo "Arquivos gravados no container são perdidos a cada novo deploy."
    echo ""
    echo "No Railway → serviço maylove-storages → Settings → Volumes → Add Volume"
    echo "  Mount path: /var/maylove/storage"
    echo ""
    echo "Depois defina a variável:"
    echo "  STORAGE_ROOT=/var/maylove/storage"
    echo ""
    echo "Ou via CLI (no projeto Railway):"
    echo "  railway volume add -m /var/maylove/storage"
    echo ""
    exit 1
  fi
fi

mkdir -p \
  "${STORAGE_ROOT}" \
  "${STORAGE_ROOT}/tmp" \
  "${STORAGE_ROOT}/tributes" \
  "${STORAGE_ROOT}/platform" \
  "${STORAGE_ROOT}/og"

MARKER="${STORAGE_ROOT}/.maylove-volume"
if [ ! -f "${MARKER}" ]; then
  date -u +"%Y-%m-%dT%H:%M:%SZ" > "${MARKER}"
  echo "[maylove-storages] Volume inicializado em ${STORAGE_ROOT}"
else
  echo "[maylove-storages] Volume persistente em ${STORAGE_ROOT} (desde $(cat "${MARKER}"))"
fi
