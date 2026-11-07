# 004 · queue-and-scheduler-infra — TODO
> **Spec:** 004 · **Alcance:** infra · **Módulo:** Transversal · **Prioridad:** alta · **Depende de specs:** —
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
| T-1 | ✅ | infra | S | `docker-compose.yml`, `docker-compose.prod.yml` | — |
| T-2 | ✅ | back | S | `back-laravel/routes/console.php` | — |
| T-3 | ✅ | infra | S | `Makefile`, `README.md` | T-1 |
| T-4 | ✅ | back | S | `back-laravel/tests/Feature/SchedulerTest.php` | T-2 |

---
## Detalle de Tareas

### T-1 — Configurar servicios `back-queue` y `back-scheduler` en Docker Compose
**Estado:** ✅ · **Subj:** infra · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `docker-compose.yml`
- `docker-compose.prod.yml`
**Descripción:**
Añadir las definiciones de servicio para el worker de colas (`back-queue`) y el planificador (`back-scheduler`), compartiendo la imagen de PHP FPM / CLI y la red de desarrollo y producción.
**Criterios de done:**
- [x] `docker compose config` no arroja errores de sintaxis.
- [x] `docker-compose.yml` y `docker-compose.prod.yml` configuran `back-queue` y `back-scheduler`.

### T-2 — Definir rutinas de limpieza en `console.php`
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:** `back-laravel/routes/console.php`
**Descripción:**
Registrar la tarea de limpieza de refresh tokens expirados y comandos de poda de colas fallidas mediante el facade `Schedule`.
**Criterios de done:**
- [x] Comando `auth:prune-tokens` registrado y programado diariamente a las 03:00.
- [x] Poda de jobs fallidos y batches programada.

### T-3 — Atajos de Make y documentación operativa
**Estado:** ✅ · **Subj:** infra · **Complejidad:** S · **Depende de:** T-1
**Archivos clave:** `Makefile`, `README.md`
**Descripción:**
Agregar `make queue-logs`, `make queue-restart`, `make scheduler-logs` a la sección de desarrollo del Makefile y documentarlos en el README.
**Criterios de done:**
- [x] `make help` lista los nuevos comandos con colores.
- [x] `README.md` actualizado con comandos operativos.

### T-4 — Test de integración para purga de tokens
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** T-2
**Archivos clave:** `back-laravel/tests/Feature/SchedulerTest.php`
**Descripción:**
Crear un test que inserte tokens expirados y activos en `refresh_tokens`, ejecute el comando o closure del scheduler y verifique que solo los expirados fueron eliminados.
**Criterios de done:**
- [x] `make test-filter FILTER=SchedulerTest` pasa en verde con 2 tests y 7 aserciones.
- [x] Suite completa de 75 tests pasa al 100% en `make test`.
- [x] Pint pasa sin observaciones.
