# Guía de Reinicio Completo del Stack (Reset Runbook)

Este documento detalla el procedimiento para realizar una **purga total y reconstrucción limpia desde cero** de todo el entorno de desarrollo del monolito modular (**Laravel 12 + Vue 3 + PostgreSQL 16**): contenedores, volúmenes de base de datos, imágenes compiladas, migraciones con UUIDs y datos iniciales de prueba (seeds) para autenticación y roles.

---

## ⚡ Ejecución Rápida (One-Liner)

Si deseas purgar, levantar y sembrar todo el entorno en un solo comando:

```bash
docker compose down -v --rmi local --remove-orphans && \
docker compose -f docker-compose.test.yml down -v --remove-orphans && \
docker compose up -d --build && \
sleep 4 && \
docker compose exec back-app php artisan migrate:fresh --seed
```

O utilizando el `Makefile`:

```bash
make clean && make dev && sleep 4 && make migrate-fresh
```

---

## 📋 Proceso Paso a Paso Explicado

### Paso 1: Purga Total (Clean Wipe)
Detiene los contenedores de desarrollo y testing, borra el volumen persistente de PostgreSQL (`pgdata_dev`) y elimina las imágenes locales compiladas de PHP-FPM:

```bash
# Bajar stack de desarrollo con eliminación de volúmenes (-v) e imágenes locales (--rmi local)
docker compose down -v --rmi local --remove-orphans

# Bajar stack de pruebas si quedó algún contenedor o volumen remanente
docker compose -f docker-compose.test.yml down -v --remove-orphans
```

> [!WARNING]
> El flag `-v` destruye de manera irreversible el volumen `pgdata_dev`. Todas las tablas, usuarios y datos creados en tu PostgreSQL local serán eliminados.

---

### Paso 2: Reconstrucción y Levantamiento Limpio
Al levantar nuevamente, Docker compilará la imagen de desarrollo PHP 8.3 FPM con sus extensiones (`pdo_pgsql`, `pcntl`, `opcache`), inicializará PostgreSQL 16 Alpine con un volumen nuevo y arrancará Nginx en el puerto `8000`:

```bash
# Construir imágenes e iniciar servicios en segundo plano
make dev
# (Equivalente: docker compose up -d --build)
```

Verifica que todos los contenedores estén en estado saludable (`Up` / `healthy`):
```bash
make dev-ps
# (Equivalente: docker compose ps)
```

---

### Paso 3: Ejecutar Migraciones y Seeds
Ejecuta la creación de todas las tablas de dominio con claves primarias **UUID** (`users`, `clients`, `roles`, `permissions`, `refresh_tokens`) y siembra los roles y usuarios de prueba:

```bash
make migrate-fresh
# (Equivalente: docker compose exec back-app php artisan migrate:fresh --seed)
```

**Lo que realiza este comando:**
1. **Migraciones con UUID:** Estructura las tablas con `$table->uuid('id')->primary()` y claves foráneas `foreignUuid`.
2. **Roles y Permisos (`RolePermissionSeeder`):** Configura los roles `admin`, `manager` y `user` con sus respectivos permisos granulares vía Spatie Permission.
3. **Usuarios de Prueba (`UserSeeder`):** Crea cuentas de prueba para cada rol con contraseñas listas para probar la autenticación Dual JWT.
4. **Clientes Demo (`ClientSeeder`):** Crea registros iniciales para el módulo de clientes.

---

### Paso 4: Iniciar el Frontend (Modo Híbrido)
En una terminal separada, levanta el frontend en el host con Hot Module Replacement (HMR) instantáneo:

```bash
make front-dev
# (O directamente: cd front-vue && pnpm dev)
```

El frontend estará disponible en **`http://localhost:5173`** y redirigirá de forma transparente todas las peticiones `/api` al backend Docker en `http://localhost:8000`.

---

### Paso 5: Verificación del Estado del Sistema

#### 1. Comprobación de Healthcheck de la API
```bash
curl -I http://localhost:8000/up
# Debe retornar: HTTP/1.1 200 OK
```

#### 2. Comprobación de la Suite de Pruebas
Para no vaciar los datos de desarrollo mediante `RefreshDatabase`, ejecuta las pruebas en el entorno de testing dockerizado aislado:
```bash
make test
```
> [!NOTE]
> `make test` levanta temporalmente un contenedor PostgreSQL dedicado (`postgres-test` en `5434`), corre las 16 pruebas y limpia su propio volumen sin afectar tu base de datos de desarrollo (`laravel_dev`).


---

## 🌐 URLs de Acceso y Servicios

| Entorno / Servicio | URL / Host | Puerto | Descripción |
| :--- | :--- | :--- | :--- |
| **Frontend Vue 3 (SPA)** | [http://localhost:5173](http://localhost:5173) | `5173` | Interfaz web de usuario (Host / Vite) |
| **Backend API (Laravel)** | [http://localhost:8000](http://localhost:8000) | `8000` | Endpoints API REST y autenticación |
| **API Healthcheck** | [http://localhost:8000/up](http://localhost:8000/up) | `8000` | Monitor de salud del servidor |
| **PostgreSQL (Desarrollo)** | `localhost` | `5432` | BD accesible por DBeaver (`laravel_dev`) |
| **PostgreSQL (Testing)** | `localhost` | `5434` | BD para pruebas aisladas (`laravel_test`) |

---

## 🔑 Credenciales por Defecto para Pruebas de Auth

Todas las cuentas de prueba comparten la contraseña: **`password`**

| Rol | Correo Electrónico | Contraseña | Permisos / Alcance |
| :--- | :--- | :--- | :--- |
| **Super Administrador** | `admin@example.com` | `password` | Acceso total al sistema, gestión de roles y usuarios |
| **Gerente Operativo** | `manager@example.com` | `password` | Gestión de usuarios, clientes y reportes |
| **Operador de Ventas** | `user@example.com` | `password` | Lectura, creación y actualización de clientes |
| **Usuario Demo** | `demo@example.com` | `password` | Cuenta estándar de prueba |

---

## 🐘 Conexión desde DBeaver

Para conectarte a la base de datos de desarrollo mediante DBeaver u otro cliente SQL:

- **Driver:** `PostgreSQL`
- **Host:** `localhost` o `127.0.0.1`
- **Port:** `5432`
- **Database:** `laravel_dev`
- **Username:** `laravel`
- **Password:** `secret`
- **SSL:** Desactivado (`disabled` / `none`)
