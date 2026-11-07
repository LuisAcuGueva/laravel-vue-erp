# laravel-mvc

CRUD de **clientes** (`clients`) en Laravel 12 con la arquitectura MVC clásica: modelo Eloquent, controlador resource, Form Requests, paginación y vistas Blade.

Es la versión "directa" del ejemplo. Su gemelo `laravel-clean` implementa el mismo CRUD con Clean Architecture, para poder comparar ambas organizaciones.

## Requisitos

- PHP 8.2 o superior (probado con 8.3, extensión `pdo_pgsql` habilitada)
- Composer
- Docker y Docker Compose v2

## Inicio rápido

```bash
git clone <url-del-repositorio> laravel-mvc
cd laravel-mvc

cp .env.example .env          # solo la primera vez
composer install
php artisan key:generate      # solo si APP_KEY está vacío

docker compose up -d          # levanta PostgreSQL (ver "Base de datos")
php artisan migrate
php artisan db:seed --class=ClientSeeder   # opcional: 10 clientes de prueba

php artisan serve --port=8001
```

Abre http://localhost:8001/clients.

## Base de datos

La base PostgreSQL está definida en `docker-compose.yml` de este proyecto. **`laravel-clean` declara el mismo contenedor**, así que ambos proyectos usan una sola base, `laravel_shared`.

- El archivo usa `name: laravel-shared-db`. Compose trata los dos proyectos como uno solo: levantar uno u otro reutiliza el mismo contenedor `laravel_pg`.
- Los valores salen de `.env`. Los dos `.env` deben tener las mismas credenciales de base de datos; si no coinciden, Compose recrea el contenedor.
- Los valores por defecto (`secret`, etc.) son solo para desarrollo local. **No los reutilices en otro entorno.**

| Parámetro | Valor de desarrollo |
|-----------|---------------------|
| Host | `127.0.0.1` |
| Puerto | `5432` |
| Base | `laravel_shared` |
| Usuario | `laravel` |
| Contraseña | `secret` |

Comandos útiles:

```bash
docker compose ps                      # estado del contenedor
docker compose logs -f postgres        # logs
docker compose down                    # detiene (conserva los datos)
docker compose down -v                 # detiene y BORRA los datos
docker exec -it laravel_pg psql -U laravel -d laravel_shared   # consola SQL
```

> **Importante:** como ambos proyectos comparten la tabla `migrations`, la migración `2026_10_05_000000_create_clients_table.php` debe existir con el mismo nombre y esquema en los dos. Si la cambias en uno, cámbiala en el otro.

## Rutas

Generadas con `Route::resource('clients', ClientController::class)`:

| Método | URI | Nombre | Acción |
|--------|-----|--------|--------|
| GET | `/clients` | `clients.index` | Listado paginado (10 por página, más recientes primero) |
| GET | `/clients/create` | `clients.create` | Formulario de alta |
| POST | `/clients` | `clients.store` | Crear cliente |
| GET | `/clients/{client}` | `clients.show` | Detalle |
| GET | `/clients/{client}/edit` | `clients.edit` | Formulario de edición |
| PUT/PATCH | `/clients/{client}` | `clients.update` | Actualizar cliente |
| DELETE | `/clients/{client}` | `clients.destroy` | Eliminar (soft delete) |

`/` muestra la página de bienvenida por defecto de Laravel.

## Modelo de datos

Tabla `clients`:

| Columna | Tipo | Reglas |
|---------|------|--------|
| `id` | bigint, PK | autoincremental |
| `name` | varchar(255) | obligatorio |
| `email` | varchar(255) | obligatorio, formato email, **único** |
| `phone` | varchar(30) | opcional |
| `address` | varchar(255) | opcional |
| `created_at`, `updated_at` | timestamp | automáticos |
| `deleted_at` | timestamp | soft delete |

## Estructura

```
app/
├── Http/
│   ├── Controllers/ClientController.php   # CRUD: valida, persiste, redirige
│   └── Requests/
│       ├── StoreClientRequest.php         # reglas de alta (email único)
│       └── UpdateClientRequest.php        # reglas de edición (ignora el propio email)
└── Models/Client.php                      # Eloquent, SoftDeletes
database/
├── factories/ClientFactory.php
├── migrations/2026_10_05_000000_create_clients_table.php
└── seeders/ClientSeeder.php
resources/views/
├── layouts/app.blade.php                  # layout común y mensajes flash
└── clients/                               # index, create, edit, show, _form
routes/web.php
tests/Feature/ClientTest.php
docker-compose.yml                         # PostgreSQL compartido
```

## Guía de estudio

Orden sugerido para entender el flujo de una petición. Cada paso dice qué mirar.

1. **`routes/web.php`**: una sola línea, `Route::resource`. Ver qué rutas genera con `php artisan route:list`.
2. **`app/Http/Controllers/ClientController.php`**: cada método es corto. Fijarse en que recibe `Client $client` ya resuelto (route model binding) y en `paginate(10)` del listado.
3. **`app/Http/Requests/StoreClientRequest.php`** y **`UpdateClientRequest.php`**: la validación vive aquí, no en el controlador. Comparar la regla `unique` de cada uno.
4. **`app/Models/Client.php`**: `$fillable` controla la asignación masiva; `SoftDeletes` cambia qué hace `delete()`.
5. **`database/migrations/…create_clients_table.php`**: el esquema real de la tabla.
6. **`resources/views/clients/`**: `_form.blade.php` se reutiliza en alta y edición; `layouts/app.blade.php` muestra los mensajes flash.
7. **`tests/Feature/ClientTest.php`**: cada test describe una historia de usuario. Ejecutar con `php artisan test`.

Preguntas para discutir:
- ¿Por qué la validación de email único está en el Form Request y no en el modelo?
- ¿Qué pasa con el email de un cliente borrado con soft delete? ¿Por qué?
- ¿Qué cambiaría si el listado no pudiera usar `paginate()` de Eloquent?

Ejercicios propuestos:
- Agregar un filtro por nombre en el listado (`?search=`).
- Agregar un test que verifique que el email se guarda en minúsculas (hoy no lo hace).
- Mostrar el rango de la página actual ("Mostrando 11 a 20 de 25") usando los métodos del paginador.

## Decisiones de diseño

- **Route model binding**: el controlador recibe directamente `Client $client`; si no existe, Laravel responde 404.
- **Validación en Form Requests**, no en el controlador. La regla `unique:clients,email` en update usa `Rule::unique(...)->ignore($this->route('client'))` para permitir conservar el mismo email.
- **Soft delete**: `destroy` no borra la fila. Un email de un cliente eliminado sigue ocupado, porque el índice único de la base también lo incluye.
- **Paginación ordenada por `id`** (no por `created_at`): las fechas pueden empatar y eso haría inestable la paginación.
- **Flash messages**: cada acción redirige con `status` y el layout lo muestra.
- **Vistas con CSS inline**: no requiere compilar assets con Vite.

## Tests

```bash
php artisan test
```

Los tests usan SQLite en memoria (ver `phpunit.xml`), así que **no tocan la base compartida de PostgreSQL**. `ClientTest` cubre listado y paginación, alta, validaciones, email duplicado, edición, detalle, 404 implícito y soft delete.

## Estilo

```bash
vendor/bin/pint
```
