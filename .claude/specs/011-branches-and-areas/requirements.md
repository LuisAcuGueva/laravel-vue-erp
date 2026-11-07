# 011 · branches-and-areas — Requirements
> **Spec:** 011 · **Alcance:** fullstack · **Módulo:** Maintenance · **Prioridad:** alta · **Depende de specs:** 003, 008
> **Status:** Draft
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
> **Origen:** Migración Multinort — [docs/plan_migracion_modulos_multinort.md](../../../docs/plan_migracion_modulos_multinort.md) (Fase 1)
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/) — reglas duras del repo (ej. UUIDs en claves primarias).
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints de la API REST.
> - [Users module](../../../back-laravel/app/Modules/Users/) — spec 003.
> - Origen Multinort: `create_branches_table.php`, `create_areas_table.php`, `create_branch_users_table.php`.
>
---
## 1. Contexto de Negocio
El ERP es **multi-sede en una sola base de datos**: almacenes, cajas, series de comprobantes y ventas pertenecen a una sede, y cada usuario opera en una o varias sedes. Las **áreas** (Ventas, Administración, Almacén…) clasifican al personal (spec 012) y a los requerimientos de compra (spec 022).

En Multinort, `branches` tiene `code`, `is_main`, `address`, `phone`, `email` y `tenants_uuid`; `branch_users` vincula usuarios y sedes sin sede por defecto; `areas` solo tiene `name`. El Template no tiene ninguno de estos conceptos (`users` solo tiene nombre, email, estado y roles).

Este spec crea sedes y áreas, asigna sedes a usuarios con **una sede por defecto**, y define la **sede activa** de cada petición. Los módulos de inventario, caja y ventas filtran por ella.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Administrador | Rol `admin` | JWT · `branches.*`, `areas.*`, `users.update`; accede a todas las sedes activas |
| Usuario operativo | Cajero, vendedor, almacenero | JWT · opera solo en sus sedes asignadas |

## 3. User Stories (formato EARS)
### US-1 — CRUD de sedes
**Como** administrador **quiero** registrar, editar, desactivar y eliminar sedes **para** reflejar la estructura física de la empresa.
**Cuando** se invoca `GET|POST /api/v1/branches` o `GET|PUT|DELETE /api/v1/branches/{id}`, **el sistema debe** listar con paginación y búsqueda, o crear, actualizar o eliminar (borrado lógico) la sede.
**Módulo:** `Maintenance` (`/api/v1/branches`)
**Permisos:** `branches.view`, `branches.create`, `branches.update`, `branches.delete`
**Criterios de aceptación:**
- [ ] `code` (≤ 10, mayúsculas y dígitos) y `name` (≤ 150) obligatorios y únicos entre sedes no eliminadas → 422 si se repiten.
- [ ] Campos opcionales: `address`, `ubigeo` (6 dígitos), `phone`, `email`; flags `is_main` e `is_active`.
- [ ] Listado con filtros `search` (código/nombre) e `is_active`, `meta` de paginación y orden por `code`.
- [ ] La respuesta incluye `users_count`.
- [ ] UI: `/maintenance/branches` con `ServerDataTable`, drawer de formulario y confirmación de borrado con `useConfirm`; botones según `can()`.

### US-2 — Sede principal única
**Como** administrador **quiero** marcar una sede como principal **para** que sea la sede por defecto de documentos y usuarios nuevos.
**Cuando** se crea o actualiza una sede con `is_main = true`, **el sistema debe** desmarcar a la sede principal anterior en la misma transacción.
**Módulo:** `Maintenance`
**Permisos:** `branches.update`
**Criterios de aceptación:**
- [ ] Siempre hay exactamente una sede principal cuando existe al menos una sede (la primera sede creada queda principal).
- [ ] Desmarcar `is_main` en la sede principal → 422 "Debe existir una sede principal; marque otra sede como principal".
- [ ] Eliminar o desactivar la sede principal → 422.
- [ ] Eliminar una sede con usuarios asignados → 422 "La sede tiene usuarios asignados".

### US-3 — CRUD de áreas
**Como** administrador **quiero** gestionar las áreas internas **para** clasificar al personal y los requerimientos.
**Cuando** se invoca `GET|POST /api/v1/areas` o `GET|PUT|DELETE /api/v1/areas/{id}`, **el sistema debe** gestionar el área con `name` único (≤ 100), `description` opcional e `is_active`.
**Módulo:** `Maintenance` (`/api/v1/areas`)
**Permisos:** `areas.view`, `areas.create`, `areas.update`, `areas.delete`
**Criterios de aceptación:**
- [ ] Nombre duplicado entre áreas no eliminadas → 422.
- [ ] UI: `/maintenance/areas` con el mismo patrón que sedes.

### US-4 — Opciones para selects
**Como** usuario autenticado **quiero** obtener sedes y áreas activas en formato liviano **para** llenar selects en otros módulos.
**Cuando** se solicita `GET /api/v1/branches/options` o `GET /api/v1/areas/options`, **el sistema debe** devolver `[{ id, code?, name }]` solo de registros activos, sin paginar.
**Módulo:** `Maintenance`
**Permisos:** autenticado
**Criterios de aceptación:**
- [ ] 200 para cualquier usuario autenticado; los registros inactivos o eliminados no aparecen.

### US-5 — Asignar sedes a un usuario
**Como** administrador **quiero** asignar una o varias sedes a un usuario y elegir su sede por defecto **para** limitar dónde opera.
**Cuando** se envía `PUT /api/v1/users/{id}/branches` con `{ "branch_ids": [...], "default_branch_id": "..." }`, **el sistema debe** sincronizar las sedes y marcar la sede por defecto.
**Módulo:** `Users` (`/api/v1/users/{id}/branches`)
**Permisos:** `users.update`
**Criterios de aceptación:**
- [ ] `branch_ids` con al menos una sede activa; `default_branch_id` debe estar incluido en `branch_ids` → si no, 422.
- [ ] `GET /api/v1/users` y `GET /api/v1/users/{id}` incluyen `branches: [{ id, code, name, is_default }]`.
- [ ] La asignación queda en el activity log del usuario.
- [ ] UI: en el drawer de usuario, `VAutocomplete` múltiple de sedes + select de sede por defecto.

### US-6 — Sede activa por petición
**Como** usuario operativo **quiero** elegir en la barra superior la sede en la que estoy trabajando **para** que caja, almacén y ventas muestren solo los datos de esa sede.
**Cuando** una petición a un endpoint con contexto de sede trae la cabecera `X-Branch-Id`, **el sistema debe** validar que la sede esté activa y asignada al usuario (o que el usuario sea `admin`) y usarla como sede activa; sin cabecera, usa la sede por defecto del usuario.
**Módulo:** Transversal
**Permisos:** autenticado
**Criterios de aceptación:**
- [ ] `GET /auth/me` incluye `branches: [{ id, code, name, is_default }]`; para `admin`, todas las sedes activas.
- [ ] Sede no asignada o inactiva → 403 "No tiene acceso a la sede seleccionada".
- [ ] Usuario sin sedes en un endpoint con contexto de sede → 409 "El usuario no tiene sedes asignadas".
- [ ] UI: selector de sede en el navbar. La sede activa es **por pestaña** (`sessionStorage`); `localStorage` solo guarda la última elegida como valor inicial de pestañas nuevas. Cambiar de sede en una pestaña no altera las demás.
- [ ] Toda petición del frontend lleva `X-Branch-Id`, incluidas las descargas de archivos (PDF, CSV, adjuntos), que se hacen con `$api` como blob y nunca con enlaces directos.

### US-7 — Acceso a documentos de otras sedes accesibles
**Como** usuario con varias sedes **quiero** abrir el enlace a un documento de otra de mis sedes sin cambiar la sede activa **para** consultarlo sin errores.
**Cuando** se consulta o se ejecuta una acción sobre un registro concreto (`GET|PUT|POST /…/{id}`) que pertenece a una sede, **el sistema debe** autorizarlo si la sede del registro está entre las sedes accesibles del usuario, sin exigir que coincida con la sede activa. Los listados sí filtran por la sede activa.
**Módulo:** Transversal
**Permisos:** los del recurso
**Criterios de aceptación:**
- [ ] Registro de una sede accesible distinta de la activa → 200.
- [ ] Registro de una sede no accesible → 404 (no se revela su existencia).
- [ ] Las **altas** usan siempre la sede activa (nunca un `branch_id` del body).

## 4. Reglas de negocio e invariantes
- Exactamente una sede `is_main = true` mientras exista al menos una sede no eliminada.
- La sede principal no puede eliminarse ni desactivarse.
- Un usuario tiene como máximo una sede por defecto, y esa sede está entre sus sedes asignadas.
- Un usuario no `admin` solo puede operar en sedes asignadas y activas.
- Los servicios de dominio no leen la sede activa: la reciben como parámetro (`Branch` o `branch_id`) desde el controller o el FormRequest, para funcionar igual en jobs, comandos, scheduler y tests.
- Autorización por registro: un registro con `branch_id` es visible y operable si su sede está entre las accesibles del usuario; la sede activa solo determina listados y altas.
- `code` de sede es inmutable una vez que la sede tiene documentos asociados (se aplica desde los specs 016 y 027; aquí se documenta).

## 5. Requisitos no funcionales
- Resolver la sede activa cuesta como máximo una consulta, cacheada por petición.
- Auditoría (spec 007) de sedes, áreas y asignaciones.
- Pint, Larastan, `make test` y `npm run lint` en verde.

## 6. Out of Scope
- Horarios de sede y excepciones → descartados (no se migran Citas).
- Series y correlativos por sede → spec 027.
- Almacenes y cajas por sede → specs 013 y 018.
- Catálogo de ubigeo → spec futuro.
- Ficha de personal (empleados) → spec 012.

## 7. Open Questions
- [ ] ¿Se deben ocultar del listado de usuarios los usuarios que no comparten sede con el usuario que consulta (no admin)? — Responsable: Luis Acuña.

## 8. Métricas de éxito
- `BranchesApiTest`, `AreasApiTest`, `UserBranchesApiTest`, `ActiveBranchMiddlewareTest` y `BranchAccessGuardTest` en verde.
- Los specs 013, 018 y 027 aplican el middleware de sede activa sin lógica propia de resolución.
