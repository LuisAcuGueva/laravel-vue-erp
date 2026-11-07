# 005 · api-resilience-and-exceptions — TODO
> **Spec:** 005 · **Alcance:** back · **Módulo:** Transversal · **Prioridad:** alta · **Depende de specs:** —
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
| T-1 | ✅ | back | S | `back-laravel/app/Support/Http/ApiResponse.php` | — |
| T-2 | ✅ | back | M | `back-laravel/bootstrap/app.php` | T-1 |
| T-3 | ✅ | back | S | `back-laravel/app/Http/Middleware/EnsureRequestId.php`, `SecurityHeaders.php` | — |
| T-4 | ✅ | back | S | `back-laravel/app/Providers/AppServiceProvider.php`, `back-laravel/routes/api.php` | — |
| T-5 | ✅ | back | S | `back-laravel/app/Modules/Clients/Controllers/ClientController.php`, `ClientService.php`, `MakeModuleCommand.php` | — |
| T-6 | ✅ | back | M | `back-laravel/tests/Feature/ExceptionHandlingTest.php`, `RateLimitingTest.php` | T-2, T-4 |

---
## Detalle de Tareas

### T-1 — Crear Trait de respuesta unificada `ApiResponse`
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:** `back-laravel/app/Support/Http/ApiResponse.php`
**Descripción:** Implementar métodos reutilizables `successResponse`, `errorResponse`, `createdResponse` y `noContentResponse` asegurando un envelope predecible.
**Criterios de done:**
- [x] Trait `ApiResponse` creado bajo `App\Support\Http`.
- [x] Soporte para metadatos de paginación (`meta`).

### T-2 — Configurar manejador centralizado en `bootstrap/app.php`
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1
**Archivos clave:** `back-laravel/bootstrap/app.php`
**Descripción:** Registrar en `$exceptions->render()` y `$exceptions->respond()` las respuestas estructuradas para `ValidationException` (422), `UnauthorizedException` / `AccessDeniedHttpException` (403), `AuthenticationException` (401), `NotFoundHttpException` (404), `ThrottleRequestsException` (429) y `Throwable` (500).
**Criterios de done:**
- [x] Todas las excepciones API devuelven JSON uniforme con `{ success: false, message: ... }`.
- [x] Encabezado `X-Request-Id` propagado a las respuestas de error.

### T-3 — Implementar Middlewares `EnsureRequestId` y `SecurityHeaders`
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:** `back-laravel/app/Http/Middleware/EnsureRequestId.php`, `SecurityHeaders.php`, `bootstrap/app.php`
**Descripción:** Inyectar cabecera `X-Request-Id` al log context y a las respuestas HTTP, así como cabeceras estándar de protección en el grupo global de middleware.
**Criterios de done:**
- [x] `X-Request-Id` presente en todas las respuestas de la API.
- [x] `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN` y `Referrer-Policy` aplicados.

### T-4 — Configurar Rate Limiters para API y Login
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:** `back-laravel/app/Providers/AppServiceProvider.php`, `back-laravel/routes/api.php`
**Descripción:** Definir rate limiters `api` (120 req/min) y `login` (5 req/min) y asociarlos mediante middleware `throttle:login` en `/auth/login` y `throttle:api` en `/v1/*`.
**Criterios de done:**
- [x] Intentos excesivos en `/auth/login` responden con código HTTP 429 y clave `retry_after`.

### T-5 — Auditoría y corrección de firmas UUID (`string $id`)
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/app/Modules/Clients/Controllers/ClientController.php`
- `back-laravel/app/Modules/Clients/Services/ClientService.php`
- `back-laravel/app/Modules/Clients/Models/Client.php`
- `back-laravel/app/Console/Commands/MakeModuleCommand.php`
**Descripción:** Sustituir todo uso de `int|string $id` por `string $id` conforme a las reglas duras del repositorio.
**Criterios de done:**
- [x] `ClientController`, `ClientService` y la plantilla de `MakeModuleCommand` usan exclusivamente `string $id`.

### T-6 — Tests de Feature para excepciones y limitadores
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2, T-4
**Archivos clave:** `back-laravel/tests/Feature/ExceptionHandlingTest.php`, `RateLimitingTest.php`
**Descripción:** Probar que peticiones inválidas devuelven la estructura JSON exacta y códigos 401, 403, 404, 422 y 429 con cabecera `X-Request-Id`.
**Criterios de done:**
- [x] `make test-filter FILTER=ExceptionHandlingTest` pasa al 100%.
- [x] `make test-filter FILTER=RateLimitingTest` pasa al 100%.
- [x] Suite completa de 81 tests pasa en verde en `make test`.
