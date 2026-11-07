# 007 · audit-trail — TODO
> **Spec:** 007 · **Alcance:** fullstack · **Módulo:** Audit · **Prioridad:** media · **Depende de specs:** 001, 002, 003, 005
> **Status:** Done
> **Última actualización:** 2026-10-14
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md), [design.md](./design.md).
>
> **Convenciones:** 🔧 pendiente · ✅ hecho · ⚪ opcional · Subj: `back` | `front` | `infra` | `docs` · Compl: S ≤ 1 h, M ≤ ½ día.
>
---
## Mapa de Tareas
| ID | Estado | Subj | Compl | Archivos clave | Depende de |
|---|---|---|---|---|---|
| T-1 | ✅ | back | S | `back-laravel/composer.json`, `config/activitylog.php` | — |
| T-2 | ✅ | back | M | `back-laravel/database/migrations/xxxx_create_activity_log_table.php`, `app/Modules/Audit/Models/Activity.php` | T-1 |
| T-3 | ✅ | back | S | `app/Modules/Clients/Models/Client.php`, `app/Models/User.php` | T-2 |
| T-4 | ✅ | back | M | `app/Modules/Audit/Controllers/AuditLogController.php`, `routes/api.php`, `AuditLogResource.php` | T-2 |
| T-5 | ✅ | back | S | `back-laravel/database/seeders/RolePermissionSeeder.php` | T-4 |
| T-6 | ✅ | back | M | `back-laravel/tests/Feature/Modules/Audit/AuditLogTest.php` | T-4 |
| T-7 | ✅ | front | M | `front-vue/src/modules/audit/**`, `front-vue/src/pages/audit/index.vue` | T-4 |

---
## Detalle de Tareas

### T-1 — Instalar `spatie/laravel-activitylog` y publicar configuración
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:** `back-laravel/composer.json`, `config/activitylog.php`
**Descripción:** Requerir versión compatible y configurar el modelo de actividad personalizado con UUID.

### T-2 — Migración adaptada a UUIDs y modelo `Activity`
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1
**Archivos clave:**
- `back-laravel/database/migrations/xxxx_create_activity_log_table.php`
- `back-laravel/app/Modules/Audit/Models/Activity.php`
**Descripción:** Crear migración con `$table->uuid('id')->primary()` y polimorfismo UUID para subject y causer. Crear modelo `Activity` con `HasUuids`.

### T-3 — Integrar Trait `LogsActivity` en modelos de negocio
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** T-2
**Archivos clave:** `back-laravel/app/Modules/Clients/Models/Client.php`, `app/Models/User.php`
**Descripción:** Configurar `getActivitylogOptions()` registrando cambios en dirty attributes y excluyendo contraseñas.

### T-4 — Módulo backend de consulta de auditoría
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:** `back-laravel/app/Modules/Audit/**`, `routes/api.php`
**Descripción:** Crear `AuditLogService`, `AuditLogController`, `AuditLogResource` y registrar ruta `v1/audit-logs` protegida con `permission:audit.view`.

### T-5 — Registrar permisos de auditoría en seeder
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** T-4
**Archivos clave:** `back-laravel/database/seeders/RolePermissionSeeder.php`
**Descripción:** Agregar permiso `audit.view` al rol `admin`.

### T-6 — Tests de Feature para auditoría
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-4
**Archivos clave:** `back-laravel/tests/Feature/Modules/Audit/AuditLogTest.php`
**Descripción:** Validar que al actualizar un cliente se genere el log y que un usuario sin permiso reciba 403.

### T-7 — Implementar módulo y vistas en frontend
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-4
**Archivos clave:** `front-vue/src/modules/audit/**`, `src/pages/audit/index.vue`
**Descripción:** Crear store de auditoría y componente `AuditDetailDrawer` reutilizable para consultar el historial de cambios.
