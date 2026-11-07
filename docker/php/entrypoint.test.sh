#!/bin/sh
set -e

echo "[test-entrypoint] Esperando conexión con PostgreSQL en $DB_HOST:$DB_PORT..."

# Esperar a que la base de datos de test esté lista
until pg_isready -h "${DB_HOST:-postgres-test}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-laravel}" -d "${DB_DATABASE:-laravel_test}" > /dev/null 2>&1; do
  echo "[test-entrypoint] PostgreSQL aún no está listo. Reintentando en 1s..."
  sleep 1
done

echo "[test-entrypoint] PostgreSQL listo. Ejecutando migraciones de prueba..."
php artisan migrate:fresh --force

echo "[test-entrypoint] Ejecutando suite de pruebas..."
if [ "$#" -eq 0 ]; then
  exec php artisan test
else
  exec "$@"
fi
