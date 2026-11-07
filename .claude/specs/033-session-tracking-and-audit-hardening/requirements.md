# 033 · session-tracking-and-audit-hardening — Requirements
> **Spec:** 033 · **Alcance:** fullstack · **Módulo:** Auth/Profile + Audit (transversal) · **Prioridad:** alta · **Depende de specs:** 005, 007, 009
> **Status:** In Progress
> **Última actualización:** 2026-10-23
> **Autores:** Luis Acuña Guevara
> **Origen:** Requerimiento del dueño del producto — hardening de la trazabilidad de sesiones y del activity log, a partir de una revisión de la tabla `activity_log` en producción.
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/) — reglas duras del repo (UUIDs en claves primarias, multi-sede, sesión multi-pestaña).
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints de la API REST.
> - [back-laravel/app/Modules/Auth/Models/RefreshToken.php](../../../back-laravel/app/Modules/Auth/Models/RefreshToken.php) — modelo actual de token de refresco (hoy también hace de "sesión").
> - [back-laravel/app/Modules/Auth/Services/JwtService.php](../../../back-laravel/app/Modules/Auth/Services/JwtService.php) — emisión, rotación y revocación de tokens.
> - [back-laravel/app/Modules/Profile/Services/ProfileService.php](../../../back-laravel/app/Modules/Profile/Services/ProfileService.php) — `listSessions()` / `revokeOtherSessions()` actuales.
> - [back-laravel/app/Modules/Audit/Models/Activity.php](../../../back-laravel/app/Modules/Audit/Models/Activity.php) — modelo `Activity` extendido de Spatie, con el hook que ya inyecta `branch_id`.
> - [.claude/specs/007-audit-trail/](../007-audit-trail/) — instalación original de `spatie/laravel-activitylog` (sigue en estado Draft pese a estar implementada).
> - [.claude/specs/009-user-profile-and-sessions/](../009-user-profile-and-sessions/) — diseño original de "Sesiones y Dispositivos" sobre `refresh_tokens` directamente.
>
---
## 1. Contexto de Negocio

El sistema usa autenticación JWT *stateless* (`JwtAuthenticate`, cookies `refresh_token`/`csrf_token` HttpOnly, ver `.agents/rules/auth-session.md`). Hoy el concepto de "sesión de un dispositivo/navegador" **no existe como entidad propia**: vive disuelto dentro de `refresh_tokens`, y eso produce tres problemas medibles en código y en datos reales:

1. **La tabla `sessions` nunca se llena.** `config/session.php` define `SESSION_DRIVER=database` apuntando a una tabla `sessions`; esa tabla **sí existe** — la crea `0001_01_01_000000_create_users_table.php` (el *stub* por defecto de Laravel, con esquema de framework: `id` string, `payload`, `last_activity`) — pero `routes/api.php` no activa el grupo de middleware `web`/`StartSession` (la API es JWT stateless), así que nunca recibe filas por el flujo normal de la API (`routes/web.php` sí conserva la ruta de bienvenida por defecto bajo el grupo `web`, así que en teoría una visita a `/` sí podría escribir una fila, pero es irrelevante para el negocio). Esa tabla de framework **no es** la sesión de negocio que el usuario necesita; se **reemplaza** por la tabla de dominio `sessions` que este spec introduce (mismo nombre, esquema distinto — ver `design.md` §6.1).

2. **`refresh_tokens` mezcla dos responsabilidades.** `JwtService::createTokenPair()` guarda `ip_address`/`user_agent` en cada fila de `refresh_tokens`, y `JwtService::rotateRefreshToken()` **crea una fila nueva en cada rotación** (cada `jwt.access_expires_in` = 900s por defecto) revocando la anterior con periodo de gracia. Un único login de un navegador genera decenas de filas por día, cada una con su propio `created_at`. Como consecuencia, `ProfileService::listSessions()` — que filtra `whereNull('revoked_at')` — muestra como "inicio de sesión" el `created_at` de la **última rotación**, no el del login real, y no existe ningún identificador que hilvane el historial de rotaciones de un mismo dispositivo.

3. **El activity log no sabe qué sesión/dispositivo causó un cambio.** `App\Modules\Audit\Models\Activity::booted()` ya inyecta `branch_id` en `properties` leyendo `app(ActiveBranch::class)` si está *bound*; no existe un mecanismo equivalente para `session_id`, y el causer (`causer_type`/`causer_id`) depende del driver de auth implícito de Spatie, que **no funciona en jobs en cola** (evidencia real: `SendElectronicDocumentJob` deja logs de `einvoice_status` con `causer_type`/`causer_id` y `branch_id` en `NULL`).

4. **El activity log, además, está estandarizado de forma incompleta.** Una exportación real de `activity_log` (ver Open Questions / anexo en `design.md`) muestra:
   - `subject_type` en tres formatos distintos para el mismo tipo de evento: FQCN completo (`App\Modules\Users\Models\User`, `DocumentSeries`, `BankAccount`, `ExchangeRate`, `PurchaseRequest`, `ProviderPrice` — modelos ausentes de todo `Relation::morphMap`), alias `snake_case` de morph map por módulo (`sale`, `quotation`, `purchase_order`, `catalog_item`, etc. — definidos en `PurchasesServiceProvider`, `FinanceServiceProvider`, `InventoryServiceProvider`, `SalesServiceProvider`), y un alias roto: `CashSession` sale como `'CashSession'` (PascalCase sin namespace) en vez de `'cash_session'` porque `FinanceServiceProvider` registra **ambas** claves apuntando a la misma clase y Eloquent resuelve `getMorphClass()` hacia la primera declarada.
   - `description` usado indistintamente como el nombre de evento (`'updated'`) o como una frase humana libre (`'Reprogramación de cuotas'`, `'Límites de stock actualizados'`, `'branches_synced'`, `'closed'`), sin relación estable con la columna `event` (que en esos mismos casos queda `NULL` o duplica `description`).
   - `causedBy()` **se omite** en varias llamadas manuales (`UserBranchService::syncBranches()`, `RoleService` al sincronizar permisos), confiando en el resolutor de auth implícito de Spatie — funciona en requests HTTP pero no en jobs/comandos.
   - `batch_uuid` está siempre `NULL`: operaciones de negocio que generan varios registros relacionados en una sola transacción (una venta emitida que actualiza `DocumentSeries.last_number`, `BankAccount.balance` y `catalog_item.last_purchase_cost`) no se agrupan.
   - `getActivitylogOptions()` usa dos convenciones sin criterio documentado: `logAll()->logExcept([...])` en modelos maestros/financieros vs. `logOnly([...])` en documentos transaccionales de alto volumen (`Sale::logOnly(['status','einvoice_status'])`).

5. **El spec 007-audit-trail sigue en `Draft`** pese a que `spatie/laravel-activitylog` ya está instalado y `LogsActivity` ya se usa en ~40 modelos de dominio. Este spec 033 no reabre 007 — lo trata como dependencia — pero estandariza y corrige lo que 007 dejó sin gobernanza explícita.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Usuario autenticado | Cualquier usuario con sesión activa | Token JWT Bearer + cookie `refresh_token`/`csrf_token` |
| Proceso del sistema (job/comando) | Jobs en cola (`SendElectronicDocumentJob`, etc.), comandos `artisan`, scheduler | Sin contexto HTTP; debe declarar explícitamente su causer/sesión, nunca leerlos de un singleton de request |
| Auditor / Administrador | Usuario con permiso `audit.view` | Token JWT Bearer, permiso Spatie `audit.view` |

## 3. User Stories (formato EARS)

### US-1 — Sesión de dispositivo persistente y desacoplada del refresh token
**Como** usuario autenticado,
**quiero** que mi sesión de navegador/dispositivo se identifique de forma estable desde el login hasta que la cierre, aunque mi token de acceso rote varias veces,
**para** ver en "Sesiones y Dispositivos" la fecha real de inicio de sesión y no la de la última rotación.
**Cuando** hago login (`POST /api/v1/auth/login`), **el sistema debe** crear una fila en `sessions` (con `ip_address`, `user_agent`, `created_at` = inicio real) y una fila en `refresh_tokens` con `session_id` apuntando a ella.
**Cuando** se rota el refresh token (`POST /api/v1/auth/refresh`), **el sistema debe** crear una nueva fila de `refresh_tokens` **reutilizando el mismo `session_id`**, revocar la fila anterior con periodo de gracia (comportamiento ya existente) y actualizar `sessions.last_used_at`/`ip_address`/`user_agent` de forma transaccional.
**Módulo:** Auth (`/api/v1/auth`)
**Permisos:** autenticado (login es público; refresh/logout requieren cookie válida)
**Criterios de aceptación:**
- [ ] Una sesión sobrevive N rotaciones consecutivas sin cambiar su `id` ni su `created_at`.
- [ ] `refresh_tokens.ip_address` y `refresh_tokens.user_agent` dejan de existir como columnas; viven solo en `sessions`.
- [ ] `refresh_tokens.session_id` es `NOT NULL` y FK a `sessions.id`.

### US-2 — Revocación a nivel de sesión, no de fila de token
**Como** usuario autenticado,
**quiero** que cerrar sesión, cambiar mi contraseña o pulsar "Cerrar sesión en los demás dispositivos" revoque la sesión completa (no solo la fila de refresh token vigente),
**para** que una sesión marcada como cerrada no pueda seguir rotando tokens aunque una petición concurrente esté en vuelo.
**Cuando** se revoca una sesión (`logout`, `revokeOtherSessions`, cambio de password), **el sistema debe**, dentro de una única transacción con `lockForUpdate()` sobre la fila de `sessions` (orden determinista por `id`, igual que las operaciones financieras de `.agents/rules/finance.md`), marcar `sessions.revoked_at = now()` y revocar la fila de `refresh_tokens` activa asociada.
**Cuando** se intenta rotar un refresh token cuya sesión ya tiene `revoked_at` no nulo, **el sistema debe** rechazar la rotación con 401, incluso si la fila de `refresh_tokens` todavía no estaba marcada como revocada.
**Módulo:** Auth/Profile (`/api/v1/auth/logout`, `/api/v1/profile/sessions/others`, `/api/v1/profile/password`)
**Permisos:** autenticado, solo sobre las propias sesiones
**Criterios de aceptación:**
- [ ] Revocar una sesión impide cualquier rotación futura de sus refresh tokens, incluso dentro del periodo de gracia.
- [ ] `revokeOtherSessions` opera sobre `sessions`, no sobre `refresh_tokens` directamente.
- [ ] Dos revocaciones concurrentes de la misma sesión no producen condiciones de carrera (verificado con lock determinista).

### US-3 — Listado de sesiones con datos de dispositivo estructurados
**Como** usuario autenticado,
**quiero** ver mis sesiones activas con tipo de dispositivo y navegador ya resueltos por el backend,
**para** no depender de un parseo de `user_agent` duplicado y frágil en el frontend.
**Cuando** se listan sesiones (`GET /api/v1/profile/sessions`), **el sistema debe** devolver por cada sesión: `id`, `device_type` (`desktop`|`mobile`|`tablet`|`unknown`), `browser`, `platform`, `ip_address`, `created_at` (login real), `last_used_at`, `is_current`.
**Módulo:** Profile (`/api/v1/profile/sessions`)
**Permisos:** autenticado, solo las propias
**Criterios de aceptación:**
- [ ] El parseo de `user_agent` ocurre una sola vez, al crear/actualizar la sesión en el backend; el frontend consume campos ya estructurados.
- [ ] `front-vue/src/modules/profile/components/SessionsTab.vue` deja de tener `getDeviceIcon`/`getBrowserName` basados en regex sobre `user_agent` crudo.

### US-4 — `session_id` en toda actividad registrada
**Como** auditor,
**quiero** que cada fila de `activity_log` generada dentro de una sesión identifique esa sesión,
**para** poder responder "¿qué cambió este dispositivo/sesión concreta?" sin ambigüedad, incluyendo cuando el cambio ocurre en un job en cola iniciado por una acción de un usuario.
**Cuando** se registra una actividad dentro de una request HTTP autenticada, **el sistema debe** incluir `session_id` en `properties` (igual mecanismo que `branch_id` hoy), resuelto desde el token de acceso validado, nunca desde un singleton leído implícitamente en capas de dominio.
**Cuando** un job en cola genera actividad como consecuencia de una acción de un usuario (p. ej. `SendElectronicDocumentJob`), **el sistema debe** recibir `session_id`, `causer_id`/`causer_type` y `branch_id` como parte del payload del job (serializados al encolar), nunca resueltos desde `ActiveBranch`/contexto de sesión en el worker.
**Módulo:** Audit (transversal)
**Permisos:** — (infraestructura interna)
**Criterios de aceptación:**
- [ ] Un test de integración verifica que una actividad generada dentro de una request autenticada lleva `session_id` en `properties`.
- [ ] Un test de integración verifica que `SendElectronicDocumentJob` (o el job elegido como caso de referencia) deja de generar logs con `causer_type`/`causer_id`/`branch_id` nulos cuando fue encolado desde una acción de usuario.

### US-5 — Activity log estandarizado y consistente entre módulos
**Como** desarrollador del equipo,
**quiero** una convención única y verificable para `subject_type` (morph map), `event`/`description`, `causedBy()` explícito y agrupación por `batch_uuid`,
**para** que cualquier módulo nuevo registre actividad de forma predecible y que los módulos existentes con inconsistencias se corrijan.
**Cuando** un modelo de dominio es auditable, **el sistema debe** tener su alias registrado en `Relation::morphMap()` sin duplicados ni colisiones (se corrige el caso `CashSession`/`cash_session`), y los modelos hoy ausentes del morph map (`User`, `DocumentSeries`, `BankAccount`, `ExchangeRate`, `PurchaseRequest`, `ProviderPrice`, …) deben incorporarse.
**Cuando** se registra actividad manualmente (fuera del ciclo automático de `LogsActivity`), **el sistema debe** usar un helper común que exija `event` (slug de máquina, de un enum por módulo) y `causedBy()` explícito; `description` se deriva de `event`+`subject` salvo override explícito.
**Cuando** una operación de negocio genera varias actividades relacionadas en una misma transacción, **el sistema debe** agruparlas con `batch_uuid` (`withBatch()` de Spatie).
**Módulo:** Audit (transversal)
**Permisos:** — (infraestructura interna)
**Criterios de aceptación:**
- [ ] Un test de regresión recorre todas las llamadas a `Relation::morphMap()` del proyecto y falla si dos claves distintas apuntan a la misma clase (detecta duplicados tipo `CashSession`/`cash_session`).
- [ ] Un test de regresión falla si algún modelo con `LogsActivity` no tiene alias en `morphMap`.
- [ ] Las 5 llamadas manuales identificadas (`InstallmentScheduleService`, `StockQueryService`, `UserBranchService`, `PurchaseOrderWorkflowService`, `RoleService`) migran al helper común.

## 4. Reglas de negocio e invariantes
- Una sesión (`sessions`) tiene exactamente una fila de `refresh_tokens` **activa** (no revocada) a la vez; nunca cero mientras la sesión esté viva, nunca más de una.
- `sessions.revoked_at` es la única fuente de verdad para "¿esta sesión sigue viva?"; una fila de `refresh_tokens` puede estar revocada por rotación (con periodo de gracia) sin que la sesión esté revocada, pero si la sesión está revocada ningún refresh token asociado puede rotar ni emitir un nuevo access token.
- Revocar/rotar una sesión bloquea la fila de `sessions` con `lockForUpdate()` antes de leer su estado, para evitar condiciones de carrera entre pestañas/requests concurrentes (mismo criterio que `.agents/rules/finance.md` para operaciones de dinero).
- `session_id` y `branch_id` en `properties` de `activity_log` se resuelven **siempre explícitamente** desde la capa de presentación (controller/middleware) o desde el payload serializado de un job; ninguna capa de servicio de dominio llama `app(ActiveBranch::class)` ni a un resolutor equivalente de sesión (coherente con `.agents/rules/multi-branch.md`).
- `causedBy()` es obligatorio y explícito en toda llamada manual a `activity()`; nunca se depende del auth driver implícito de Spatie.
- `Relation::morphMap()` no admite dos claves apuntando a la misma clase ni modelos auditables sin alias.

## 5. Requisitos no funcionales
- Las migraciones de `sessions`/`refresh_tokens` deben ser reversibles (`down()`) y no deben interrumpir sesiones activas en producción de forma destructiva (plan de migración de datos de `refresh_tokens` existentes hacia `sessions` incluido en `design.md`).
- El listado de sesiones (`GET /api/v1/profile/sessions`) debe responder en <100ms para un usuario con hasta 20 sesiones activas.
- Ocultar `token_hash`/`csrf_token_hash` en toda respuesta JSON (ya se cumple; no debe regresar con el refactor).
- Toda tabla nueva usa PK UUID (`.agents/rules/database-primary-keys.md`).

## 6. Out of Scope
- Geolocalización de IP o enriquecimiento de `ip_address` con proveedor externo — posible spec futuro.
- 2FA — ya marcado out of scope en 009 y se mantiene.
- Reescribir el spec 007-audit-trail completo o migrar su estado a `Done` — este spec solo agrega gobernanza y corrige lo roto, 007 queda como referencia histórica de la instalación inicial.
- UI de auditoría global (`front-vue/src/pages/audit/index.vue`) — ya mencionada como "opcional" en 007; no se construye en este spec salvo que se decida en `design.md` que es trivial de incluir.
- Sustituir `spatie/laravel-activitylog` por otra librería.

## 7. Open Questions
- [x] ¿Se elimina `SESSION_DRIVER=database`/`config/session.php` por completo o se documenta como "no usado"? **Resuelto distinto a lo planteado**: ninguna de las dos — se cambia `SESSION_DRIVER` a `array` en `.env`/`.env.example`/`docker-compose.yml`/`docker-compose.prod.yml`. "Documentar que no se usa" resultó insuficiente: `auth()->login()` toca el almacén de sesión incluso fuera de un request HTTP (ver `design.md` §7, bug real reproducido con `make migrate-fresh`).
- [ ] Parseo de `user_agent`: ¿regex propia ligera o dependencia nueva (p. ej. `whichbrowser/parser`)? — Definir en `design.md` §1/§6 evaluando que hoy no hay ninguna librería de este tipo en `composer.json`.
- [ ] ¿El job de referencia para US-4 es `SendElectronicDocumentJob` u otro con más superficie (p. ej. jobs de `StockLedgerService`)? — Confirmar con el dueño del producto antes de `spec-tasks`.

## 8. Métricas de éxito
- 0 filas nuevas en `refresh_tokens` con `ip_address`/`user_agent` (columnas eliminadas).
- 100% de los modelos con `LogsActivity` presentes en `Relation::morphMap()` sin duplicados.
- 0 llamadas manuales a `activity()` sin `causedBy()` explícito tras la migración de las 5 identificadas.
- Tests de integración en verde para US-1 a US-5 vía `make test`.
