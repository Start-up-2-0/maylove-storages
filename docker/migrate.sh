#!/bin/sh
set -e

if [ "${APP_ENV}" != "prod" ]; then
  echo ""
  echo "ERROR: APP_ENV deve ser exatamente \"prod\" (valor atual: \"${APP_ENV:-<vazio>}\")."
  echo ""
  echo "No Railway → serviço maylove-storages → Variables:"
  echo "  APP_ENV = prod"
  echo ""
  exit 1
fi

if [ -z "${APP_SECRET}" ]; then
  echo ""
  echo "ERROR: APP_SECRET não está definida."
  echo ""
  exit 1
fi

if [ -z "${DATABASE_URL}" ]; then
  echo ""
  echo "ERROR: DATABASE_URL não está definida."
  echo ""
  echo "No Railway → serviço maylove-storages → Variables → Add Reference:"
  echo "  MySQL → MYSQL_PRIVATE_URL → nome: DATABASE_URL"
  echo ""
  exit 1
fi

if [ -z "${STORAGE_ROOT}" ]; then
  echo ""
  echo "ERROR: STORAGE_ROOT não está definida."
  echo ""
  echo "Monte um volume em /var/maylove/storage e defina:"
  echo "  STORAGE_ROOT=/var/maylove/storage"
  echo ""
  exit 1
fi

mkdir -p "${STORAGE_ROOT}"

echo "Running pending Doctrine migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
