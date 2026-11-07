---
description: Inicializa un spec numerado bajo .claude/specs/NNN-<feature>/ con los 4 archivos esqueleto del flujo SDD (monolito modular Laravel 12 + Vue 3 para software a medida).
argument-hint: <feature-kebab>
---

# /spec-init — Inicializar un spec numerado

Vas a crear un nuevo spec bajo `.claude/specs/NNN-$ARGUMENTS/` siguiendo el flujo Spec-Driven Development del proyecto. `NNN` es un correlativo de 3 dígitos que indica **el orden de creación** de los specs; nunca se reutiliza ni se renumera.

> **Contexto del repo:** Monolito modular para software a medida con backend API y frontend SPA:
> - `back-laravel/` — Laravel 12 (PHP 8.3), PostgreSQL 16 Alpine. Arquitectura modular en `app/Modules/<Modulo>/` (Controllers, Models, Requests, Resources, Services, Providers) con generador automatizado `php artisan make:module`, rutas en `routes/api.php` bajo prefijo `v1/`, migraciones en `database/migrations/` con PKs UUID (`.agents/rules/database-primary-keys.md`). Orquestado vía Docker (`make dev-up`, `make test`, `Makefile`).
> - `front-vue/` — Vue 3 + Vite + Vuetify 3 + Pinia + CASL, `unplugin-vue-router`, MSW para mocks de endpoints, administrado con `npm` (`make front-dev`).
>
> Todo spec nace en `.claude/specs/`.

## Pasos

1.  **Validar argumento:**
    *   `$ARGUMENTS` es el nombre del feature **sin número**, en kebab-case inglés (`^[a-z][a-z0-9-]*[a-z0-9]$`). Si trae prefijo numérico (`003-foo`) o no es kebab-case, abortar y pedir corrección.
    *   Si ya existe un spec con el mismo sufijo (`ls -d .claude/specs/[0-9][0-9][0-9]-$ARGUMENTS 2>/dev/null`), abortar — no sobreescribas trabajo en curso, sin importar su número.

2.  **Calcular el número `NNN`** vía Bash:
    ```bash
    last=$(ls -d .claude/specs/[0-9][0-9][0-9]-*/ 2>/dev/null | sed -E 's#.*/([0-9]{3})-[^/]*/$#\1#' | sort -n | tail -1)
    printf '%03d\n' $(( 10#${last:-0} + 1 ))
    ```
    *   Sin specs previos → `001`. Con `001-x` y `003-y` → `004` (los huecos no se rellenan).
    *   Directorio final: `.claude/specs/NNN-$ARGUMENTS/` (en adelante `<SPEC>`).

3.  **Resolver metadata:**
    *   Fecha actual: `date +%Y-%m-%d`.
    *   Autor: `git config user.name`.
    *   **Alcance** (`back` | `front` | `fullstack` | `infra`), **Módulo** (`Clients`, `Auth`, `Billing`, `Transversal`, etc.), **Prioridad** (`alta` | `media` | `baja`) y **Depende de specs** (`00X, 00Y` o `—`): inferirlos del requerimiento y del código; si hay ambigüedad, preguntarlos con AskUserQuestion.
    *   **Origen**: issue, requerimiento de negocio o `—`.

4.  **Crear los 4 archivos** con los esqueletos de abajo, reemplazando `{{NNN}}`, `{{FEATURE}}`, `{{DATE}}`, `{{AUTHOR}}`, `{{SCOPE}}`, `{{MODULE}}`, `{{PRIORITY}}`, `{{DEPENDS}}`, `{{ORIGIN}}`. Ningún placeholder `{{…}}` puede quedar en los archivos generados.

5.  **Actualizar el índice** `.claude/specs/README.md` (crearlo si no existe, con el encabezado de abajo) agregando una fila al final de la tabla.

6.  **Cierre:** mostrar el path creado y el próximo paso: `/spec-requirements NNN`.

---

### Índice `.claude/specs/README.md` (solo si no existe)

```markdown
# Specs — índice

> Specs del flujo SDD del proyecto (Monolito Modular Laravel 12 + Vue 3), numerados por **orden de creación** (`NNN-feature`). El número nunca se reutiliza.
> Comandos: `/spec-init` → `/spec-requirements` → `/spec-design` → `/spec-tasks` → `/spec-CLAUDE.md.delta` → `/spec-impl`.
> Reglas del repo: [.agents/rules/](../../.agents/rules/) · Makefile: [Makefile](../../Makefile) · Visión general: [README.md](../../README.md)

**Estados:** `Draft` → `Approved` → `In Progress` → `Done`

| # | Spec | Alcance | Módulo | Prioridad | Estado | Depende de specs | Origen |
|---|---|---|---|---|---|---|---|
```

Fila a agregar: `| {{NNN}} | [{{FEATURE}}](./{{NNN}}-{{FEATURE}}/requirements.md) | {{SCOPE}} | {{MODULE}} | {{PRIORITY}} | Draft | {{DEPENDS}} | {{ORIGIN}} |`

---

### `<SPEC>/requirements.md`

```markdown
# {{NNN}} · {{FEATURE}} — Requirements
> **Spec:** {{NNN}} · **Alcance:** {{SCOPE}} · **Módulo:** {{MODULE}} · **Prioridad:** {{PRIORITY}} · **Depende de specs:** {{DEPENDS}}
> **Status:** Draft
> **Última actualización:** {{DATE}}
> **Autores:** {{AUTHOR}}
> **Origen:** {{ORIGIN}}
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/) — reglas duras del repo (ej. UUIDs en claves primarias).
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints de la API REST.
>
---
## 1. Contexto de Negocio
(Por qué existe este feature, qué problema resuelve en la aplicación a medida y para quién. Citar el estado actual con código real.)

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| | | |

## 3. User Stories (formato EARS)
### US-1 — (título)
**Como** … **quiero** … **para** ….
**Cuando** `<trigger>`, **el sistema debe** `<comportamiento observable>`.
**Módulo:** `{{MODULE}}` (`/api/v1/...`)
**Permisos:** `<modulo.accion>` (Spatie / CASL) | público | autenticado
**Criterios de aceptación:**
- [ ] …

## 4. Reglas de negocio e invariantes
- (Invariantes que el modelo y la lógica de negocio deben garantizar.)

## 5. Requisitos no funcionales
- (Seguridad, rendimiento, validación estricta, calidad: Pint, PHPUnit vía `make test`, ESLint en front.)

## 6. Out of Scope
- (Qué NO entra en este spec y en qué spec o entrega se resuelve.)

## 7. Open Questions
- [ ] (Decisiones pendientes, con responsable.)

## 8. Métricas de éxito
- (Medibles: tests en verde, tiempos de respuesta, flujos cubiertos.)
```

---

### `<SPEC>/design.md`

```markdown
# {{NNN}} · {{FEATURE}} — Design
> **Spec:** {{NNN}} · **Alcance:** {{SCOPE}} · **Módulo:** {{MODULE}} · **Prioridad:** {{PRIORITY}} · **Depende de specs:** {{DEPENDS}}
> **Status:** Draft
> **Última actualización:** {{DATE}}
> **Autores:** {{AUTHOR}}
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md) — define el qué/por qué; este documento define el cómo.
> **Reglas:** [.agents/rules/](../../../.agents/rules/) · **Makefile:** [Makefile](../../../Makefile)
>
---
## 0. Ubicación y alcance
(Backend: `app/Modules/<Modulo>/` con Model, Controller, Service, Request, Resource, Provider registrado en `bootstrap/providers.php`, o estructura estándar de Laravel. Frontend: `front-vue/src/pages/<modulo>/`, `src/views/`, `src/stores/`. Marcar "No aplica" en secciones que no correspondan.)

## 1. Backend — Modelo y Persistencia
(Modelo Eloquent en `app/Modules/<Modulo>/Models/` o `app/Models/`. Clave primaria UUID obligatoria con `use HasUuids;`, `$keyType = 'string'`, `$incrementing = false`. Casts, scopes de búsqueda y SoftDeletes si aplica.)

## 2. Backend — Lógica de Negocio y Servicios
(Clase de servicio en `app/Modules/<Modulo>/Services/<Singular>Service.php` con métodos tipados: `list`, `findById`, `create`, `update`, `delete`.)

## 3. Backend — Presentation (Controllers, Requests, Resources)
(Controlador en `Controllers/`, FormRequests tipados con validación estricta en `Requests/` con `authorize() => true`, Resource en `Resources/` con estructura JSON controlada. Rutas en `routes/api.php` bajo `/api/v1/<kebab-module>` con `->whereUuid('id')`.)

## 4. Contrato API
| Método | Ruta | Middleware / Permiso | Request Body | Respuesta exitosa | Errores posibles |
|---|---|---|---|---|---|
| | | | | | |
(Ejemplos JSON de request y respuesta: `{ "data": ... }` y errores 422 `{ "message": "...", "errors": { "campo": [...] } }`. Mensajes al usuario en español.)

## 5. Permisos y seguridad
(Permisos Spatie / CASL en frontend (`useAcl().can()`), autenticación Sanctum/JWT, políticas de autorización.)

## 6. Migraciones
(DDL completo como migración Laravel `Schema::create(...)` en `back-laravel/database/migrations/YYYY_MM_DD_HHMMSS_<nombre>.php`. PK UUID obligatoria `$table->uuid('id')->primary()`, FKs con `foreignUuid()`. Nunca editar migraciones ya aplicadas.)

## 7. Configuración e infraestructura
(`.env`, `config/*.php`, `docker-compose*.yml`, `Makefile`. Variables de entorno nuevas con sus valores por defecto.)

## 8. Frontend
(Páginas en `front-vue/src/pages/`, componentes Vuetify 3 (`VCard`, `VDataTable`, `VDialog`, etc.), store Pinia, composables (`useApi`), handlers MSW para mocks en desarrollo/testing. Estados de carga, vacío y error.)

## 9. Testing
(Backend: Feature Tests en `back-laravel/tests/Feature/Modules/<Modulo>/` ejecutables con `make test` o `make test-filter FILTER=<NombreTest>`. Frontend: linter `npm run lint` y pruebas unitarias si aplica.)

## 10. Decisiones Rechazadas
(Alternativas consideradas y motivos por los que se descartaron.)
```

---

### `<SPEC>/todo.md`

```markdown
# {{NNN}} · {{FEATURE}} — TODO
> **Spec:** {{NNN}} · **Alcance:** {{SCOPE}} · **Módulo:** {{MODULE}} · **Prioridad:** {{PRIORITY}} · **Depende de specs:** {{DEPENDS}}
> **Status:** Draft
> **Última actualización:** {{DATE}}
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md), [design.md](./design.md).
>
> **Convenciones:**
> - 🔧 = pendiente · ✅ = hecho · ⚪ = opcional
> - **Subj** (subproyecto): `back` | `front` | `infra` | `docs`
> - **Complejidad:** S = ≤ 1 h · M = ≤ ½ día · L = > ½ día (evitar L: partir en varias M)
> - Cada tarea es **autocontenida**: un agente con `requirements.md`, `design.md` y los archivos indicados puede ejecutarla sin contexto adicional.
> - **Dependencias** explícitas entre tareas → preservar el orden indicado.
>
---
## Mapa de Tareas
| ID | Estado | Subj | Compl | Archivos clave | Depende de |
|---|---|---|---|---|---|
| T-1 | 🔧 | | | | — |

---
## Detalle de Tareas
### T-1 — (título)
**Estado:** 🔧 · **Subj:** back/front/infra/docs · **Complejidad:** S/M/L · **Depende de:** —
**Archivos clave:**
- `back-laravel/app/...`
- `back-laravel/tests/...`
**Descripción:**
1. …
**Tests:**
- `<Archivo de test>` — qué verifica.
**Criterios de done:**
- [ ] …
- [ ] Backend: `make test` (o `make test-filter FILTER=<Test>`) y `cd back-laravel && vendor/bin/pint --test` en verde.
- [ ] Frontend: `cd front-vue && npm run lint` en verde.
---
```

---

### `<SPEC>/CLAUDE.md.delta.md`

```markdown
# {{NNN}} · {{FEATURE}} — Delta de reglas
> **Instrucciones:** este archivo contiene **solo las reglas duras nuevas** que este spec agrega al repo. Al cerrar el spec se mergean en [.agents/rules/](../../../.agents/rules/) (archivo existente o uno nuevo con frontmatter `trigger` / `description`), según el destino indicado en cada sección.
>
> **Origen:** [requirements.md](./requirements.md), [design.md](./design.md), [todo.md](./todo.md).
>
---
## (+ en ".agents/rules/<archivo>.md") (título)
- (Regla dura nueva, concreta y verificable.)
```
