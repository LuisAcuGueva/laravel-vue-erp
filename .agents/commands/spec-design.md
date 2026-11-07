---
description: Genera o refina .claude/specs/NNN-<feature>/design.md (Arquitectura Monolito Modular Laravel 12 + Vue 3, contrato API, permisos, migraciones UUID, config, frontend Vuetify, testing).
argument-hint: <NNN | NNN-feature | feature>
---

# /spec-design — Llenar design.md

Vas a generar (o refinar) `design.md` de un spec: el **cómo** — ubicación, capas modulares, contratos API, migraciones, frontend y testing — dentro del proyecto Monolito Modular Laravel 12 + Vue 3.

## Pasos

1.  **Resolver el spec** a partir de `$ARGUMENTS` (acepta `001`, `001-clients-crud` o `clients-crud`):
    ```bash
    ls -d .claude/specs/$ARGUMENTS/ .claude/specs/$ARGUMENTS-*/ .claude/specs/[0-9][0-9][0-9]-$ARGUMENTS/ 2>/dev/null | sort -u
    ```
    *   Exactamente un resultado → `<SPEC>`. Si no, abortar mostrando `ls .claude/specs/`.
    *   `<SPEC>/requirements.md` debe tener User Stories completas. Si no, abortar y sugerir `/spec-requirements <NNN>`.

2.  **Leer contexto:**
    *   `<SPEC>/requirements.md` — fuente de verdad del qué/por qué.
    *   `<SPEC>/design.md` — estado actual (refinar, no rehacer).
    *   [.agents/rules/](../../.agents/rules/) — reglas duras (especialmente `.agents/rules/database-primary-keys.md`).
    *   `design.md` de los specs en "Depende de specs" — reutilizar sus servicios, modelos y convenciones.
    *   **El código real** que se toca:
        *   Backend: `back-laravel/app/Modules/<Modulo>/` (Controllers, Models, Requests, Resources, Services, Providers), `bootstrap/app.php`, `bootstrap/providers.php`, `routes/api.php`, migraciones en `database/migrations/` y tests en `tests/Feature/`.
        *   Comando generador: `back-laravel/app/Console/Commands/MakeModuleCommand.php` para scaffolding modular rápido (`php artisan make:module <Name> --all --uuid`).
        *   Frontend: `front-vue/src/pages/`, `front-vue/src/views/`, `front-vue/src/composables/`, `front-vue/src/plugins/`, `front-vue/src/navigation/`.
        *   Infraestructura: `docker-compose*.yml`, `docker/`, `.env.example`, `Makefile`.
    *   Skills del repo (`.agents/skills/laravel-patterns`, `php-pro`, `vue-best-practices`, etc.) subordinadas a las reglas del proyecto.

3.  **Decidir ubicación:**
    *   Backend **Modules**: `app/Modules/<Modulo>/` para módulos de negocio (ej. `Clients`, `Products`, `Orders`) compuesto por:
        - `Controllers/<Singular>Controller.php`
        - `Models/<Singular>.php` (con `HasUuids`, `$keyType = 'string'`, `$incrementing = false`)
        - `Requests/<Singular>Request.php`, `List<Modulo>Request.php`
        - `Resources/<Singular>Resource.php`
        - `Services/<Singular>Service.php`
        - `Providers/<Modulo>ServiceProvider.php` (registrado en `bootstrap/providers.php`)
    *   Backend transversal: `app/Http/`, `app/Models/`, `app/Services/` solo si aplica a toda la aplicación de manera global.
    *   Frontend: `front-vue/src/pages/<modulo-kebab>/` para rutas automáticas (Vite + `unplugin-vue-router`), vistas y modales con componentes Vuetify 3 (`VCard`, `VDataTable`, `VDialog`, etc.), y stores en `front-vue/src/stores/`.

4.  **Producir las secciones** del esqueleto (0 a 10); las que no apliquen se marcan "No aplica" con justificación:
    *   **0. Ubicación y alcance** — justificar directorio(s) y componentes a crear o modificar.
    *   **1. Backend — Modelo y Persistencia** — definición del modelo Eloquent con UUID obligatoria (`HasUuids`), casts, soft deletes y scopes (`scopeSearch`).
    *   **2. Backend — Lógica de Negocio y Servicios** — métodos del Service (`list`, `findById`, `create`, `update`, `delete`).
    *   **3. Backend — Presentation** — Controller, FormRequests tipados con `rules()` y `toAttributes()`, Resource con estructura JSON y rutas en `routes/api.php` bajo `/api/v1/<kebab>` con `->whereUuid('id')`.
    *   **4. Contrato API** — tabla de endpoints con request y response JSON, mensajes y validaciones en español.
    *   **5. Permisos y seguridad** — permisos Spatie / CASL (`useAcl().can()`), guards y políticas.
    *   **6. Migraciones** — DDL **completo** como migración Laravel (`Schema::create(...)`) en `back-laravel/database/migrations/YYYY_MM_DD_HHMMSS_<desc>.php`. Clave primaria UUID obligatoria `$table->uuid('id')->primary()`, FKs con `foreignUuid()`.
    *   **7. Configuración e infraestructura** — variables en `.env.example`, ajustes en `config/*.php`, servicios Docker o comandos en Makefile si aplica.
    *   **8. Frontend** — página en `src/pages/<kebab>/index.vue`, componentes Vuetify, store Pinia si se comparte estado, mocks con MSW (`public/mockServiceWorker.js`).
    *   **9. Testing** — Feature Tests en `back-laravel/tests/Feature/Modules/<Modulo>/<Modulo>ApiTest.php` ejecutables vía `make test` o `make test-filter FILTER=<Modulo>ApiTest`.
    *   **10. Decisiones Rechazadas** — alternativas descartadas y motivos.

5.  **Reglas duras** (el design no puede contradecirlas):
    *   **PK UUID obligatoria:** toda tabla de dominio debe usar `$table->uuid('id')->primary();` y modelos `use HasUuids;` (`.agents/rules/database-primary-keys.md`). Rutas con `->whereUuid('id')`.
    *   **Controllers delgados:** delegan a `Service`; no contienen consultas SQL ni lógica de persistencia directa.
    *   **FormRequests estrictos:** validan inputs y exponen `toAttributes()`; `authorize()` retorna `true` (la autorización va en rutas o middleware).
    *   **Configuración desacoplada:** nada hardcodeado; usar `config()` y variables de entorno en `.env`.
    *   **Frontend reactivo y accesible:** Vue 3 Composition API (`<script setup>`), componentes Vuetify 3, manejo de errores y estados de carga (`loading`).
    *   **Idioma:** español para textos narrativos, mensajes de validación y de respuesta al usuario; inglés para identificadores de código y nombres de tablas/columnas.

6.  **Cierre:** resumir secciones generadas/refinadas. **Próximo paso:** `/spec-tasks <NNN>`.
