#!/bin/sh
set -e

# Crear directorios necesarios de almacenamiento si no existen
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

# Permisos para storage y cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# Copiar .env si no existe
if [ ! -f /var/www/html/.env ]; then
  if [ -f /var/www/html/.env.example ]; then
    echo "[docker-entrypoint] Copiando .env.example a .env..."
    cp /var/www/html/.env.example /var/www/html/.env
  fi
fi

# Generar APP_KEY si falta
if [ -f /var/www/html/.env ] && ! grep -q "^APP_KEY=base64:" /var/www/html/.env; then
  echo "[docker-entrypoint] Generando APP_KEY..."
  php artisan key:generate --force || true
fi

# Ejecutar comando
exec "$@"
