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

YOUTUBE_COOKIES_TARGET="${YOUTUBE_COOKIES_FILE:-${STORAGE_ROOT}/platform/youtube-cookies.txt}"
YOUTUBE_COOKIES_B64="$(printf '%s' "${YOUTUBE_COOKIES_B64:-}" | tr -d '\r\n\t ')"

if [ -n "${YOUTUBE_COOKIES_B64}" ]; then
  mkdir -p "$(dirname "${YOUTUBE_COOKIES_TARGET}")"
  tmp="${YOUTUBE_COOKIES_TARGET}.tmp.$$"

  if printf '%s' "${YOUTUBE_COOKIES_B64}" | base64 -d > "${tmp}" 2>/dev/null; then
    first_line="$(head -n 1 "${tmp}" 2>/dev/null || true)"
    case "${first_line}" in
      *"Netscape HTTP Cookie File"*|*"HTTP Cookie File"*)
        mv "${tmp}" "${YOUTUBE_COOKIES_TARGET}"
        chmod 600 "${YOUTUBE_COOKIES_TARGET}"
        echo "[maylove-storages] Cookies do YouTube materializados em ${YOUTUBE_COOKIES_TARGET}"
        ;;
      *)
        rm -f "${tmp}"
        echo "[maylove-storages] WARNING: YOUTUBE_COOKIES_B64 decodificado, mas não está em formato Netscape (cookies.txt)."
        echo "[maylove-storages] Exporte com extensão 'Get cookies.txt LOCALLY' ou yt-dlp --cookies-from-browser."
        ;;
    esac
  else
    rm -f "${tmp}"
    echo "[maylove-storages] WARNING: YOUTUBE_COOKIES_B64 inválido (base64). Importação YouTube seguirá sem cookies."
  fi
fi
