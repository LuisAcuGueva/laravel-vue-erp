# 012 · employees — Requirements
> **Spec:** 012 · **Alcance:** fullstack · **Módulo:** Maintenance · **Prioridad:** alta · **Depende de specs:** 003, 011
> **Status:** Draft
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
> **Origen:** Migración Multinort — [docs/plan_migracion_modulos_multinort.md](../../../docs/plan_migracion_modulos_multinort.md) (Fase 1)
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/) — reglas duras del repo (ej. UUIDs en claves primarias).
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints de la API REST.
> - Spec 011 — `Branch`, `Area`, `UserBranchService`.
> - Origen Multinort: `create_employees_table.php`, `add_columns_to_employees.php`, `EmployeeController.php`, `CreateUserService::createOrAssignForEmployee`.
>
---
## 1. Contexto de Negocio
La empresa necesita una **ficha de personal** independiente del acceso al sistema: hay trabajadores sin usuario (vendedores de piso, operarios) y usuarios que no son personal (soporte externo). La ficha registra documento, contacto, cargo, área y sede, y puede vincularse a un usuario cuando la persona necesita entrar al sistema.

En Multinort, `employees` tiene `first_name`, `last_name`, `dni`, `email`, `phone`, `address`, `area_id`, `position` y `user_id` opcional. Se usa casi exclusivamente en el taller (técnicos y asesores de ODS, elevadores, despachos, herramientas), y `EmployeeController` sincroniza los datos con el usuario vinculado. En el Template genérico, el empleado sirve como **vendedor** en cotizaciones y ventas (`seller_id`, specs 026/027) y como base para planilla o comisiones futuras.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Administrador / RR. HH. | Rol `admin` o `manager` | JWT · `employees.*`; `users.create` para crear el acceso |
| Usuario operativo | Vendedor, cajero | JWT · lectura de opciones de empleados para selects |

## 3. User Stories (formato EARS)
### US-1 — CRUD de empleados
**Como** administrador **quiero** registrar, editar, desactivar y eliminar empleados **para** mantener el padrón de personal.
**Cuando** se invoca `GET|POST /api/v1/employees` o `GET|PUT|DELETE /api/v1/employees/{id}`, **el sistema debe** listar con filtros o crear, actualizar o eliminar (borrado lógico) la ficha.
**Módulo:** `Maintenance` (`/api/v1/employees`)
**Permisos:** `employees.view`, `employees.create`, `employees.update`, `employees.delete`
**Criterios de aceptación:**
- [ ] Obligatorios: `document_type` (`DNI`, `CE`, `PASSPORT`), `document_number` (DNI = 8 dígitos; resto ≤ 15 alfanumérico), `first_name`, `last_name`. Opcionales: `email`, `phone`, `address`, `birth_date`, `hire_date`, `position` (≤ 100), `area_id`, `branch_id`, `is_active`.
- [ ] `(document_type, document_number)` único entre empleados no eliminados; `email` único si se informa → 422.
- [ ] `area_id`/`branch_id` deben existir y estar activos → 422.
- [ ] Listado con `search` (nombre, apellido, documento), filtros `branch_id`, `area_id`, `is_active`, `has_user`, paginado; cada fila incluye `full_name`, `area { id, name }`, `branch { id, code, name }`, `user { id, email, status } | null`.
- [ ] Eliminar el empleado no elimina su usuario; el vínculo desaparece.
- [ ] UI: `/maintenance/employees` con `ServerDataTable`, filtros por sede/área/estado, drawer de formulario y `useConfirm`.

### US-2 — Crear acceso al sistema desde la ficha
**Como** administrador **quiero** crear un usuario para un empleado sin acceso **para** que pueda iniciar sesión con roles y sedes.
**Cuando** se envía `POST /api/v1/employees/{id}/user` con `{ email, password, password_confirmation, roles[], branch_ids?[] }`, **el sistema debe** crear el usuario con `name = full_name`, asignar roles y sedes (por defecto la sede del empleado) y vincularlo a la ficha en una transacción.
**Módulo:** `Maintenance`
**Permisos:** `employees.update` **y** `users.create`
**Criterios de aceptación:**
- [ ] Empleado que ya tiene usuario → 422 "El empleado ya tiene un usuario vinculado".
- [ ] Las mismas validaciones de `UserRequest` (spec 003): email único, contraseña mínima y roles existentes; un actor no admin no asigna roles con permisos que no posee.
- [ ] Si `branch_ids` no llega y el empleado tiene `branch_id`, el usuario queda con esa sede como sede por defecto.
- [ ] 201 con el empleado y su `user`.

### US-3 — Vincular o desvincular un usuario existente
**Como** administrador **quiero** asociar una ficha a un usuario ya creado, o quitar el vínculo **para** reflejar casos de personas que ya tenían acceso.
**Cuando** se envía `PUT /api/v1/employees/{id}/user` con `{ user_id }` o `DELETE /api/v1/employees/{id}/user`, **el sistema debe** vincular (si el usuario no está vinculado a otro empleado) o desvincular sin modificar el usuario.
**Módulo:** `Maintenance`
**Permisos:** `employees.update`
**Criterios de aceptación:**
- [ ] Usuario ya vinculado a otro empleado → 422 "El usuario ya está vinculado al empleado <nombre>".
- [ ] Desvincular no cambia el estado del usuario.
- [ ] UI: en el drawer, bloque "Acceso al sistema" con tres estados (sin usuario: botones "Crear acceso" y "Vincular existente"; con usuario: email, estado y "Desvincular").

### US-4 — Sincronizar el nombre con el usuario vinculado
**Como** administrador **quiero** que al cambiar nombres en la ficha también cambie el nombre del usuario **para** no mantener dos datos distintos.
**Cuando** se actualizan `first_name` o `last_name` de un empleado con usuario, **el sistema debe** actualizar `users.name` con el `full_name` en la misma transacción.
**Módulo:** `Maintenance`
**Permisos:** `employees.update`
**Criterios de aceptación:**
- [ ] `users.name` queda igual a `"{first_name} {last_name}"` tras el update.
- [ ] El email del usuario **no** se sincroniza (se gestiona en Usuarios o Mi Cuenta).

### US-5 — Opciones de empleados para selects
**Como** vendedor o cajero **quiero** elegir un empleado activo en formularios (vendedor de una venta) **para** atribuir la operación.
**Cuando** se solicita `GET /api/v1/employees/options?branch_id=&search=`, **el sistema debe** devolver hasta 50 empleados activos `[{ id, full_name, document_number, position }]`.
**Módulo:** `Maintenance`
**Permisos:** autenticado
**Criterios de aceptación:**
- [ ] Solo activos y no eliminados; filtra por sede si se envía `branch_id`.

## 4. Reglas de negocio e invariantes
- Un usuario está vinculado como máximo a un empleado, y un empleado a un usuario como máximo (`employees.user_id` único).
- Un empleado inactivo no aparece en opciones, pero conserva sus referencias históricas (ventas, cotizaciones).
- Eliminar un empleado referenciado por documentos (ventas, cotizaciones) → 422; esa verificación la agregan los specs 026/027 en `EmployeeService::delete()`.
- `full_name` es siempre `first_name + ' ' + last_name` (derivado, no se persiste).

## 5. Requisitos no funcionales
- Auditoría (spec 007) de altas, cambios y vínculos.
- Búsqueda insensible a mayúsculas (`ilike`) con índice por `document_number`.
- Pint, Larastan, `make test` y `npm run lint` en verde.

## 6. Out of Scope
- Planilla, asistencia, comisiones y contratos → spec futuro (RR. HH.).
- Asignaciones del taller (técnicos, elevadores, herramientas, despachos) → descartadas por ser del rubro automotriz.
- Horarios del personal → descartados junto con Citas.
- Uso del empleado como vendedor → specs 026 (quotations) y 027 (sales-and-vouchers).

## 7. Open Questions
- [ ] ¿Desactivar un empleado debe desactivar también su usuario? Propuesta: no, solo mostrar un aviso. — Responsable: Luis Acuña.
- [ ] ¿Se requiere foto del empleado? — Responsable: Luis Acuña.

## 8. Métricas de éxito
- `EmployeesApiTest` y `EmployeeUserLinkApiTest` en verde.
- Crear el acceso desde la ficha toma un solo paso en la UI.
