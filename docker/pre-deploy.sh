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

php bin/console doctrine:migrations:migrate --no-interaction
