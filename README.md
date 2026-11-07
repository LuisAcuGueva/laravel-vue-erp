# Template Monolito Modular: Laravel 12 + Vue 3 + PostgreSQL

Este repositorio es una plantilla para software a medida que combina un backend en **Laravel 12** (PHP 8.3) y un frontend SPA en **Vue 3** (Vite + Vuetify) con **PostgreSQL 16**, orquestado con Docker y automatizado mediante un `Makefile`.

---

## 🏗️ Arquitectura de Entornos

### 1. Desarrollo (`make dev` - Modo Híbrido)
- **Base de Datos y Backend Dockerizados**:
  - PostgreSQL 16 Alpine en puerto `5432`.
  - Nginx Gateway en puerto `80` que enruta la API en `http://api.localhost` hacia PHP 8.3 FPM (`back-app`).
  - Mapeo de volumen en vivo (`./back-laravel`) para reflejar cambios instantáneamente.
  - Permisos ajustados con el UID/GID de tu usuario local para evitar problemas con `storage/` o `cache/`.
- **Frontend en el Host**:
  - Se ejecuta en el host mediante `npm run dev` (o `make front-dev`) y es servido a través del Gateway Nginx en `http://localhost` (puerto 80 sin especificar puerto).
  - Hot Module Replacement (HMR) nativo ultrarrápido sin sobrecarga de sincronización de contenedores.
  - Configurado con proxy y CORS para comunicarse directamente con `http://api.localhost`.

### 2. Testing (`make test` - Aislado y reproducible)
- Contenedor dedicado de PostgreSQL 16 (`postgres-test`).
- Contenedor de pruebas de Laravel que:
  1. Espera a que la base de datos esté lista con `pg_isready`.
  2. Ejecuta migraciones limpias (`migrate:fresh`).
  3. Ejecuta la suite de pruebas (`php artisan test`).
  4. Apaga y limpia los recursos y volúmenes temporales al terminar.

### 3. Producción (`make prod-build` / `make prod-up` - Nginx Unificado)
- **Nginx Unificado**:
  - Un único punto de entrada en el puerto `80` (o configurable).
  - Sirve el build estático optimizado de Vue 3 (generado en un build multi-etapa con Node Alpine).
  - Redirige las rutas `/api`, `/sanctum` y `/up` directamente al socket FastCGI de PHP-FPM (`back-app`).
  - **Ventaja**: Elimina problemas de CORS en producción, ya que frontend y backend comparten el mismo origen.
- **PHP 8.3 FPM Optimizado**:
  - OPcache activado y preconfigurado para alto rendimiento.
  - Autoloader optimizado (`composer install --no-dev --optimize-autoloader`).

---

## 🚀 Guía Rápida de Comandos (`make`)

Ejecuta `make help` para ver todos los comandos disponibles con descripciones y colores.

### 🛠️ Desarrollo
```bash
# 1. Iniciar Backend y Base de Datos en Docker
make dev-up
# o bien:
make dev

# 2. Iniciar Frontend en el Host (en otra terminal)
make front-dev
# o directamente: cd front-vue && npm run dev
```

- **Ver logs generales**: `make dev-logs`
- **Logs del Worker de Colas**: `make queue-logs`
- **Reiniciar Worker de Colas**: `make queue-restart`
- **Logs del Scheduler**: `make scheduler-logs`
- **Ver contenedores**: `make dev-ps`
- **Detener desarrollo**: `make dev-down`

### 🧪 Testing
```bash
# Ejecutar todas las pruebas con PostgreSQL en Docker
make test

# Ejecutar una prueba o archivo específico
make test-filter FILTER=ClientTest
```

### 🚀 Producción
```bash
# Compilar imágenes de producción (Front SPA + PHP-FPM)
make prod-build

# Levantar entorno de producción
make prod-up

# Ver logs de producción
make prod-logs

# Detener producción
make prod-down

# Catálogo de ubigeo INEI (spec 030): en cada despliegue que cambie
# back-laravel/database/data/ubigeo_inei.csv. Es idempotente.
docker compose -f docker-compose.prod.yml exec back-app php artisan db:seed --class=UbigeoSeeder --force
```

### 📦 Comandos de Laravel en Desarrollo
```bash
# Migraciones y seeders
make migrate
make migrate-fresh
make seed

# Consola interactiva
make tinker

# Ejecutar cualquier comando Artisan
make artisan CMD="route:list"
make artisan CMD="make:migration add_field_to_table"

# Diagnóstico de la configuración contable (plan de cuentas y conceptos); sale con 1 si hay errores
make artisan CMD="finance:check-accounting-setup"
make artisan CMD="finance:check-accounting-setup --json"

# Ejecutar comandos Composer
make composer CMD="dump-autoload"

# Terminal dentro del contenedor
make back-bash
make db-bash
```

---

## 📁 Estructura del Proyecto

```
├── back-laravel/           # Código fuente de Laravel 12
├── front-vue/              # Código fuente de Vue 3 (SPA)
├── docker/
│   ├── php/
│   │   ├── Dockerfile      # Multi-stage para dev, test y prod
│   │   ├── php.dev.ini     # Configuración PHP para desarrollo
│   │   ├── php.prod.ini    # OPcache y ajustes de producción
│   │   ├── entrypoint.dev.sh
│   │   └── entrypoint.test.sh
│   └── nginx/
│       ├── default.dev.conf  # Nginx para backend en dev (puerto 8000)
│       ├── default.prod.conf # Nginx unificado para prod (Front + API)
│       └── Dockerfile.prod   # Multi-stage Nginx + Vue 3 dist
├── docker-compose.yml       # Orquestación de Desarrollo
├── docker-compose.test.yml  # Orquestación de Testing
├── docker-compose.prod.yml  # Orquestación de Producción
├── Makefile                 # Automatización de tareas
├── .env.example             # Variables globales del stack
└── .dockerignore            # Exclusión de archivos pesados en builds
```
