# 007 · audit-trail — Requirements
> **Spec:** 007 · **Alcance:** fullstack · **Módulo:** Audit · **Prioridad:** media · **Depende de specs:** 001, 002, 003, 005
> **Status:** Draft
> **Última actualización:** 2026-10-14
> **Autores:** Luis Acuña Guevara
> **Origen:** Requerimiento transversal de software a medida - trazabilidad de cambios (siguiendo el patrón de `Template Multi Tenant/back-multi-tenancy`)
>
> **Referencias del proyecto:**
> - [.agents/rules/database-primary-keys.md](../../../.agents/rules/database-primary-keys.md) — UUIDs en claves primarias y foráneas.
> - [Template Multi Tenant/back-multi-tenancy/app/Core/Audit/](../../../../Template%20Multi%20Tenant/back-multi-tenancy/app/Core/Audit/) — referencia de dominio de auditoría.
>
---
## 1. Contexto de Negocio
En aplicaciones a medida de misión crítica (gestión financiera, comercial, administrativa), es mandatorio contar con una pista de auditoría (*Audit Trail*) inmutable que responda: **quién modificó un registro, en qué momento, desde qué IP y qué atributos cambiaron exactamente**.

Este spec implementa la auditoría utilizando la librería `spatie/laravel-activitylog`, adaptada rigurosamente a las reglas del proyecto (claves primarias UUID, tipos estrictos, sin dependencia rígida de guards y compatibilidad con PostgreSQL) tal como se estructuró en `Template Multi Tenant`.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Usuario Autenticado | Cualquier usuario del sistema que crea, edita o elimina entidades | JWT Bearer |
| Administrador / Auditor | Usuario con permiso `audit.view` | Permiso Spatie `audit.view` |

## 3. User Stories (formato EARS)
### US-1 — Registro automático de cambios en modelos de dominio
**Como** oficial de cumplimiento y seguridad,
**quiero** que cada creación, modificación o eliminación lógica/física en modelos clave (`Client`, `User`, `Role`) se registre automáticamente,
**para** disponer de un historial completo de cambios de atributos (`old` vs `attributes`).
**Cuando** un usuario realiza una mutación sobre una entidad con el trait `LogsActivity`, **el sistema debe** generar una entrada en la tabla de auditoría con su ID UUID, el ID del autor (`causer_id`), tipo y ID del modelo (`subject_type`, `subject_id`), propiedades alteradas e IP del cliente.

### US-2 — Consulta de logs de auditoría por API
**Como** administrador del sistema,
**quiero** consultar los registros de auditoría filtrados por rango de fecha, usuario o módulo (`/api/v1/audit-logs`),
**para** investigar incidencias operativas o verificar la actividad de los usuarios.
**Cuando** un usuario con permiso `audit.view` consulta el endpoint, **el sistema debe** retornar la lista paginada de registros con la estructura `ApiResponse`.

### US-3 — Visualización de historial de cambios en Frontend
**Como** usuario con permisos de supervisión en Vue 3,
**quiero** visualizar el componente de historial de cambios dentro del detalle de una entidad o en una vista dedicada de auditoría,
**para** inspeccionar visualmente qué usuario cambió un valor y qué valor tenía anteriormente.

## 4. Reglas de negocio e invariantes
- **Inmutabilidad:** Las entradas de auditoría no pueden ser editadas ni eliminadas vía API por ningún usuario (`DELETE` y `PUT` deshabilitados).
- **UUIDs estrictos:** La tabla `activity_log` debe usar clave primaria UUID (`id`), y las columnas polimórficas de sujeto y causante deben usar `uuidMorphs` (`subject_id` y `causer_id` de tipo UUID en PostgreSQL).
- **Campos sensibles protegidos:** Atributos como contraseñas (`password`), tokens y secretos deben excluirse explícitamente del guardado en el log de cambios.

## 5. Requisitos no funcionales
- Bajo impacto en latencia de escritura (< 5ms adicionales por operación Eloquent).
- Paginación obligatoria en endpoints de consulta (máximo 100 registros por página).

## 6. Out of Scope
- Almacenamiento en base de datos externa de series temporales (se almacena en tabla Postgres nativa).

## 7. Open Questions
- [x] ¿Librería comunitaria o módulo propio? *Decisión: Spatie activitylog adaptado con modelo personalizado UUID y traits conforme a la arquitectura de referencia.*

## 8. Métricas de éxito
- Cualquier `update` en `Client` o `User` genera un registro con diff JSON exacto.
- Endpoint `/api/v1/audit-logs` protegido con middleware `permission:audit.view`.
