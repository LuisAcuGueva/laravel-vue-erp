# 035 · security-owasp-hardening — TODO
> **Spec:** 035 · **Alcance:** fullstack · **Módulo:** Transversal (Auth + infraestructura) · **Prioridad:** alta · **Depende de specs:** 005, 007, 009, 033
> **Status:** Draft
> **Última actualización:** 2026-10-29
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md), [design.md](./design.md).
>
> **Convenciones:**
> - 🔧 = pendiente · ✅ = hecho · ⚪ = opcional
> - **Subj** (subproyecto): `back` | `front` | `infra` | `docs`
> - **Complejidad:** S = ≤ 1 h · M = ≤ ½ día · L = > ½ día (evitar L: partir en varias M)
> - Cada tarea es **autocontenida**: un agente con `requirements.md`, `design.md` y los archivos indicados puede ejecutarla sin contexto adicional.
> - **Dependencias** explícitas entre tareas → preservar el orden indicado.
> - Tests de backend **siempre** con `make test-run ARGS="--filter=X"` / `make test-filter FILTER=X` / `make test` (contenedor `postgres-test`); nunca `php artisan test` en el host (`testing.md`).
> - Tras modificar código: `graphify update .`.
>
---
## Mapa de Tareas
| ID | Estado | Subj | Compl | Archivos clave | Depende de |
|---|---|---|---|---|---|
| T-1 | 🔧 | back | S | `EnsureRequestId.php`, `tests/Unit/Security/EnsureRequestIdTest.php` | — |
| T-2 | 🔧 | back | M | `config/jwt.php`, `JwtService.php`, `.env.example`, `docker-compose*.yml`, `phpunit.xml` | — |
| T-3 | 🔧 | back | M | `SecurityLogger.php`, `config/logging.php` | — |
| T-4 | 🔧 | back | M | `SecurityHeaders.php`, `config/security.php`, `bootstrap/app.php` | — |
| T-5 | 🔧 | back | M | `AppServiceProvider.php`, `routes/api.php`, `AuthController.php`, `JwtService.php` | T-3 |
| T-6 | 🔧 | back | M | `PasswordPolicy.php`, `UserRequest.php`, `UpdatePasswordRequest.php`, `SystemParameterSeeder.php`, `ProfileController.php` | — |
| T-7 | 🔧 | back | S | `config/cors.php`, `.env.example`, `docker-compose.prod.yml` | — |
| T-8 | 🔧 | back | S | `CompanyLogoRequest.php`, `CheckCompanyLogoCommand.php` | — |
| T-9 | 🔧 | back | S | `AccountsPayableQueryService.php`, `.env.example` (APP_DEBUG/LOG_LEVEL) | T-2 |
| T-10 | 🔧 | infra | M | `docker/nginx/default.prod.conf`, `docker/nginx/snippets/security-headers.conf`, `docker/php/php.prod.ini`, `docker/nginx/Dockerfile.prod` | T-4, T-8 |
| T-11 | 🔧 | front | M | `CompanyForm.vue`, `usePasswordPolicy.js`, formularios de contraseña, MSW | T-6, T-8 |
| T-12 | 🔧 | infra | S | `Makefile`, `.github/workflows/security-audit.yml`, `.github/dependabot.yml` | — |
| T-13 | 🔧 | back | M | `tests/Feature/Security/*` (pruebas de integración transversales) | T-1…T-9 |
| T-14 | 🔧 | docs | S | `.agents/rules/*` (merge del delta), `README.md`, runbook de despliegue | T-1…T-13 |
| T-15 | ⚪ | infra | S | Promover CSP de `Report-Only` a enforcing | T-10 + verificación manual |

Orden recomendado de ejecución: T-1 → T-2 → T-3 → T-4 → T-5 → T-6 → T-7 → T-8 → T-9 → T-10 → T-11 → T-12 → T-13 → T-14 → T-15. Prioridad por severidad: primero T-2 (H-02), T-4/T-10 (H-01), T-8 (H-03).

---
## Detalle de Tareas

### T-1 — Validar `X-Request-Id` (H-09)
**Estado:** 🔧 · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/app/Http/Middleware/EnsureRequestId.php`
- `back-laravel/tests/Unit/Security/EnsureRequestIdTest.php` (nuevo)
**Descripción:**
1. Aceptar el `X-Request-Id` entrante solo si cumple `^[A-Za-z0-9-]{8,64}$` (design §2.6); en otro caso generar `Str::uuid()` y reescribir la cabecera de la request.
2. Mantener `Log::withContext(['request_id' => …])` y la cabecera en la respuesta.
**Tests:**
- `EnsureRequestIdTest` — válido se conserva; con espacios, `\n`, caracteres especiales, vacío y 65 caracteres se reemplaza por UUID; la respuesta devuelve el id efectivo.
**Criterios de done:**
- [ ] Un id malicioso nunca llega al contexto de logs.
- [ ] Backend: `make test-filter FILTER=EnsureRequestIdTest` y `cd back-laravel && vendor/bin/pint --test` en verde.

### T-2 — Secreto JWT obligatorio y plantillas de entorno seguras (H-02, H-14)
**Estado:** 🔧 · **Subj:** back · **Complejidad:** M · **Depende de:** —
**Archivos clave:**
- `back-laravel/config/jwt.php`
- `back-laravel/app/Modules/Auth/Services/JwtService.php`
- `back-laravel/app/Providers/AppServiceProvider.php` (validación al arrancar en producción)
- `back-laravel/.env.example`, `.env.example` (raíz)
- `docker-compose.prod.yml`, `docker-compose.yml` (reemplazar el literal en los 3 servicios, líneas ~44, 101 y 139), `docker-compose.test.yml` (hoy no define `JWT_SECRET`)
- `back-laravel/phpunit.xml`
- `README.md` (menciona el literal por defecto)
- `Makefile` (target nuevo `secrets`)
- `back-laravel/tests/Unit/Security/JwtSecretTest.php` (nuevo)
**Descripción:**
1. `config/jwt.php`: `'secret' => env('JWT_SECRET')`, sin `?:` ni literal.
2. `JwtService::__construct()`: eliminar los dos fallbacks (a `APP_KEY` y al literal). Si el secreto mide < 32 bytes lanzar `RuntimeException` en español (design §2.2). Soportar prefijo `base64:`.
3. `AppServiceProvider::boot()`: en producción resolver `JwtService` para fallar al arrancar.
4. `.env.example`: `APP_KEY=`, `JWT_SECRET=` (vacíos, con comentario de generación), `APP_DEBUG=false`, comentario "no copiar a producción sin completar".
5. `docker-compose.prod.yml`: `JWT_SECRET: ${JWT_SECRET:?JWT_SECRET es requerida para produccion}` en los 3 servicios. `docker-compose.yml` y `docker-compose.test.yml`: `JWT_SECRET: ${JWT_SECRET:-dev-only-jwt-secret-not-for-production-0123456789}` (≥ 32 bytes); `JwtService` rechaza en `APP_ENV=production` cualquier secreto con prefijo `dev-only-`.
6. `phpunit.xml`: `<env name="JWT_SECRET" value="…≥32 bytes de prueba…"/>`.
7. `Makefile`: **no existe hoy un target de setup**; crear `make secrets`, que solo imprime un `APP_KEY` y un `JWT_SECRET` aleatorios (no escribe archivos).
8. Verificar con `grep -rn "default-fallback-jwt-secret" . --exclude-dir=node_modules --exclude-dir=vendor --exclude-dir=graphify-out` que el literal ya no existe. Hoy aparece en `docker-compose.yml`, `config/jwt.php`, `JwtService.php`, `.env.example` y `README.md`.
**Tests:**
- `JwtSecretTest` — secreto vacío y de 31 bytes lanza; de 32 bytes funciona; no se usa `APP_KEY` como respaldo.
- Suite de Auth existente (`AuthTest`) — sigue en verde con el secreto de test.
**Criterios de done:**
- [ ] El literal por defecto no existe en el repositorio.
- [ ] `docker compose -f docker-compose.prod.yml config` sin `JWT_SECRET` falla con mensaje claro.
- [ ] Backend: `make test` y `vendor/bin/pint --test` en verde.
- [ ] Nota en el runbook (T-14): rotar la `APP_KEY` si el valor histórico se usó en un entorno real.

### T-3 — Canal de logs `security` y `SecurityLogger` (H-08)
**Estado:** 🔧 · **Subj:** back · **Complejidad:** M · **Depende de:** —
**Archivos clave:**
- `back-laravel/app/Support/Security/SecurityLogger.php` (nuevo)
- `back-laravel/config/logging.php`
- `back-laravel/.env.example` (`LOG_LEVEL=info`, `SECURITY_LOG_DAYS`)
- `back-laravel/tests/Unit/Security/SecurityLoggerTest.php` (nuevo)
**Descripción:**
1. Crear el canal `security` (driver `daily`, `storage/logs/security.log`, `days` = 90, formato JSON con `JsonFormatter`).
2. Implementar `SecurityLogger` con los métodos del design §2.5 y `sanitize()`: elimina `\r`, `\n` y caracteres de control, trunca a 200, enmascara emails y descarta las claves `password`, `current_password`, `token`, `access_token`, `refresh_token`, `csrf_token`, `authorization`, `cookie`.
3. Cada evento incluye `event`, `request_id`, `ip`, `user_id`, `user_agent`.
4. Cambiar `LOG_LEVEL` por defecto de `.env.example` a `info`.
**Tests:**
- `SecurityLoggerTest` — emite el evento esperado; un valor con `\r\nFAKE` queda en una sola línea; ningún campo prohibido aparece; el email sale enmascarado (`ab***@dominio.com`).
**Criterios de done:**
- [ ] Nunca se loguea contraseña ni token (test con valores centinela).
- [ ] Backend: `make test-filter FILTER=SecurityLoggerTest` y `pint --test` en verde.

### T-4 — Middleware `SecurityHeaders` completo (H-01, H-11)
**Estado:** 🔧 · **Subj:** back · **Complejidad:** M · **Depende de:** —
**Archivos clave:**
- `back-laravel/app/Http/Middleware/SecurityHeaders.php`
- `back-laravel/config/security.php` (nuevo: `hsts`, `rate_limits`)
- `back-laravel/bootstrap/app.php` (`trustProxies` condicional por `TRUSTED_PROXIES`)
- `back-laravel/tests/Feature/Security/SecurityHeadersTest.php` (nuevo)
**Descripción:**
1. Aplicar el middleware del design §2.1: `X-Frame-Options: DENY`, quitar `X-XSS-Protection` y `X-Powered-By`, agregar `Cross-Origin-Opener-Policy`, CSP `default-src 'none'; frame-ancestors 'none'` en respuestas de API/JSON, HSTS solo si `isSecure()` y entorno distinto de `local`/`testing`.
2. Crear `config/security.php` con `hsts` y los límites configurables que usa T-5.
3. Si `TRUSTED_PROXIES` está definido, configurar `trustProxies` con las cabeceras `X-Forwarded-For`/`X-Forwarded-Proto`.
**Tests:**
- `SecurityHeadersTest` — cabeceras presentes en `GET /api/up` y en un 401; ausencia de `X-XSS-Protection: 1; mode=block`; HSTS solo con `Request::create(..., server: ['HTTPS' => 'on'])` bajo entorno `production` simulado; sin HSTS en `testing`.
**Criterios de done:**
- [ ] Cabeceras verificadas también en respuestas de error (401/403/404/422/429/500).
- [ ] Backend: `make test-filter FILTER=SecurityHeadersTest` y `pint --test` en verde.

### T-5 — Límites en refresh/logout, login endurecido e integración de eventos (H-04, H-05)
**Estado:** 🔧 · **Subj:** back · **Complejidad:** M · **Depende de:** T-3
**Archivos clave:**
- `back-laravel/app/Providers/AppServiceProvider.php`
- `back-laravel/routes/api.php`
- `back-laravel/app/Modules/Auth/Controllers/AuthController.php`
- `back-laravel/app/Modules/Auth/Services/JwtService.php` (motivo de rechazo del refresh)
- `back-laravel/bootstrap/app.php` (handlers 403/429 → `SecurityLogger`)
- `back-laravel/app/Modules/Profile/Services/ProfileService.php` / `ProfileController.php` (eventos de contraseña y sesiones)
- `back-laravel/tests/Feature/Security/AuthRateLimitTest.php`, `LoginTimingTest.php` (nuevos)
**Descripción:**
1. Limitadores: `login` con 3 límites (5/min `email|ip`, 15/hora `email`, 30/min `ip`); `auth-refresh` y `auth-logout` 30/min por IP (valores en `config/security.php`).
2. `routes/api.php`: `->middleware('throttle:auth-refresh')` y `->middleware('throttle:auth-logout')`.
3. `AuthController::login()`: `Hash::check` siempre, con hash ficticio memoizado cuando el usuario no existe (design §2.3); mantener "inactivo" solo tras contraseña válida; registrar `login_failed`/`login_succeeded`.
4. `refresh`: registrar `refresh_rejected` con motivo (`invalid`, `csrf_mismatch`, `expired`, `revoked`). **No** cambiar los códigos 401/403 ni el manejo de cookies de `auth-session.md`.
5. `bootstrap/app.php`: en los handlers de 403 (`permission_denied`) y 429 (`rate_limited`) llamar a `SecurityLogger`; el cuerpo JSON no cambia.
6. `ProfileService`/`ProfileController`: `password_changed` y `sessions_revoked` (el servicio recibe parámetros; el controller llama al logger, ver design §2.5).
**Tests:**
- `AuthRateLimitTest` — 31.ª petición a refresh y a logout → 429; 16 logins fallidos con IPs distintas y el mismo email → 429; el refresh silencioso con 3 pestañas simuladas no supera el límite.
- `LoginTimingTest` — el hasher es invocado con usuario inexistente (spy sobre `Hash`); mismo mensaje `Credenciales incorrectas.`.
- Suite existente `RateLimitingTest`, `AuthTest` en verde.
**Criterios de done:**
- [ ] Contrato 401/403 del refresh intacto (tests de `auth-session` existentes pasan).
- [ ] Backend: `make test-filter FILTER=Auth` y `pint --test` en verde.

### T-6 — Política de contraseñas centralizada y configurable (H-06)
**Estado:** 🔧 · **Subj:** back · **Complejidad:** M · **Depende de:** —
**Archivos clave:**
- `back-laravel/app/Support/Auth/PasswordPolicy.php` (nuevo)
- `back-laravel/app/Support/Auth/SafeUncompromised.php` (nuevo, regla envoltorio fail-open)
- `back-laravel/app/Modules/Users/Requests/UserRequest.php`
- `back-laravel/app/Modules/Profile/Requests/UpdatePasswordRequest.php`
- `back-laravel/app/Modules/Profile/Controllers/ProfileController.php` (`passwordPolicy()`)
- `back-laravel/routes/api.php` (`GET /v1/profile/password-policy`)
- `back-laravel/database/seeders/SystemParameterSeeder.php`
- `back-laravel/tests/Feature/Security/PasswordPolicyTest.php` (nuevo)
**Descripción:**
1. Sembrar con `updateOrCreate` por `(group_key, item_key)`, `value_type` explícito y sin pisar `value`: `auth.password_min_length` (integer, 12), `auth.password_require_mixed_case` (boolean, true), `auth.password_require_number` (boolean, true), `auth.password_check_pwned` (boolean, false).
2. `PasswordPolicy::rules()` y `hint()` leen `SystemParameterService::get()` (design §2.4). `SafeUncompromised` captura fallos de red y loguea `warning` sin bloquear.
3. Sustituir `min:8` en `UserRequest` y `UpdatePasswordRequest`; agregar `different:current_password` en el cambio de contraseña. Mensajes en español.
4. Endpoint `GET /api/v1/profile/password-policy` (autenticado, `throttle:api`) con la forma del design §4.
5. No tocar contraseñas existentes ni sesiones activas.
**Tests:**
- `PasswordPolicyTest` — rechaza 11 caracteres, sin mayúscula, sin número; acepta una contraseña válida; con `check_pwned=true` y `Http::fake` de error de red no bloquea; con `check_pwned=false` no hace ninguna petición HTTP; el endpoint devuelve la política vigente.
- Tests existentes de usuarios y perfil actualizados a contraseñas que cumplan la política.
**Criterios de done:**
- [ ] No quedan `min:8` sueltos de contraseña (`grep -rn "min:8" back-laravel/app`).
- [ ] Backend: `make test-filter FILTER=PasswordPolicyTest` y `make test-filter FILTER=Users` en verde; `pint --test` limpio.

### T-7 — CORS por entorno (H-07)
**Estado:** 🔧 · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/config/cors.php`
- `back-laravel/.env.example`, `docker-compose.prod.yml`, `docker-compose.yml`
- `back-laravel/tests/Feature/Security/CorsConfigTest.php` (nuevo)
**Descripción:**
1. `allowed_origins` desde `CORS_ALLOWED_ORIGINS` (CSV); patrones de `localhost` solo con `APP_ENV` en `local`/`testing`; `allowed_headers` enumerados (design §7.4); `supports_credentials` se mantiene `true` sin `*`.
2. `.env.example`: orígenes de desarrollo actuales. `docker-compose.prod.yml`: `${CORS_ALLOWED_ORIGINS:?…}` sin valor por defecto. `docker-compose.yml`: orígenes de desarrollo.
**Tests:**
- `CorsConfigTest` — preflight `OPTIONS /api/v1/...` desde origen no listado sin `Access-Control-Allow-Origin`; desde listado con él y con `Access-Control-Allow-Credentials: true`; en `production` simulado el patrón `*.localhost` no aplica.
**Criterios de done:**
- [ ] Dev local sigue funcionando (SPA en `localhost:5173/5174`).
- [ ] Backend: `make test-filter FILTER=CorsConfigTest` y `pint --test` en verde.

### T-8 — Logo sin SVG y comando de verificación (H-03, parte backend)
**Estado:** 🔧 · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/app/Modules/Configuration/Requests/CompanyLogoRequest.php`
- `back-laravel/app/Modules/Configuration/Console/CheckCompanyLogoCommand.php` (nuevo, registrado en el Provider del módulo)
- `back-laravel/tests/Feature/Modules/Configuration/CompanyLogoUploadTest.php`
- `back-laravel/tests/Feature/Modules/Configuration/CheckCompanyLogoCommandTest.php` (nuevo)
**Descripción:**
1. Reglas: `['required','file','mimes:png,jpg,jpeg,webp','mimetypes:image/png,image/jpeg,image/webp','max:2048']`; mensajes en español.
2. `company:check-logo`: obtener `CompanySettingService::current()`, avisar si el archivo es SVG (extensión o MIME real) con código de salida 1; sin borrar nada.
**Tests:**
- Subir SVG con `<script>` → 422; PNG/JPG/WEBP → 200; usuario sin `configuration.update` → 403 (agregar si falta).
- El comando detecta un logo SVG y no lo elimina.
**Criterios de done:**
- [ ] Backend: `make test-filter FILTER=CompanyLogo` y `pint --test` en verde.

### T-9 — Constante SQL fija y entorno de depuración (H-15)
**Estado:** 🔧 · **Subj:** back · **Complejidad:** S · **Depende de:** T-2
**Archivos clave:**
- `back-laravel/app/Modules/Purchases/Services/AccountsPayableQueryService.php`
- `back-laravel/tests/Feature/Modules/Purchases/` (test existente de aging)
**Descripción:**
1. Reemplazar la variable local `$days` por `private const DAYS_OVERDUE_SQL` con el docblock del design §2.8; actualizar los cuatro `selectRaw` de `aging()`.
2. Verificar que el resultado del reporte de antigüedad no cambia.
**Tests:**
- Test existente de antigüedad de cuentas por pagar sin cambios en verde.
**Criterios de done:**
- [ ] Sin cambios funcionales; `make test-filter FILTER=AccountsPayable` y `pint --test` en verde.

### T-10 — nginx y PHP de producción (H-01, H-03, H-12)
**Estado:** 🔧 · **Subj:** infra · **Complejidad:** M · **Depende de:** T-4, T-8
**Archivos clave:**
- `docker/nginx/default.prod.conf`
- `docker/nginx/snippets/security-headers.conf` (nuevo)
- `docker/php/php.prod.ini` (existente; el `Dockerfile` ya lo copia a `conf.d/99-custom.ini`)
- `docker/nginx/Dockerfile.prod` (agregar `COPY docker/nginx/snippets /etc/nginx/snippets`)
**Descripción:**
1. Crear el snippet con todas las cabeceras (design §7.1) y hacer `include` en el `server` y en **cada** `location` que declare `add_header` (assets estáticos, `/storage`), porque nginx no hereda `add_header` si el `location` define el suyo.
2. La CSP de la SPA arranca como **`Content-Security-Policy-Report-Only`** (fase 1); `/storage` usa la CSP restrictiva con `sandbox` desde el inicio.
3. `server_tokens off;` y `fastcgi_hide_header X-Powered-By;` en la ubicación PHP.
4. `docker/php/php.prod.ini`: agregar `expose_php = Off` y `log_errors = On` (`display_errors = Off` ya está). No se crea un archivo nuevo.
   - `docker/nginx/Dockerfile.prod`: copiar el directorio de snippets junto a `default.prod.conf`; sin el `COPY`, `nginx -t` falla por el `include`.
5. HSTS en el snippet solo si nginx termina TLS (según la Open Question; si no, comentarlo y documentarlo).
6. No tocar el server de desarrollo (HMR de Vite).
**Tests:**
- Manual: levantar `docker-compose.prod.yml` y ejecutar `curl -sI http://localhost/`, `curl -sI http://localhost/assets/<archivo>.js`, `curl -sI http://localhost/storage/<logo>` y `curl -sI http://localhost/api/up`; confirmar que las cabeceras de US-1 están en las cuatro y que no aparece `Server: nginx/x.y.z` ni `X-Powered-By`.
- `nginx -t` dentro del contenedor sin errores.
**Criterios de done:**
- [ ] Cabeceras presentes en las cuatro rutas anteriores.
- [ ] Recorrido completo de la SPA (login, POS, finanzas, reportes, PDFs) sin violaciones de CSP en consola; resultado anotado en el PR.
- [ ] `nginx -t` correcto.

### T-11 — Frontend: logo sin SVG y política de contraseñas (H-03, H-06)
**Estado:** 🔧 · **Subj:** front · **Complejidad:** M · **Depende de:** T-6, T-8
**Archivos clave:**
- `front-vue/src/modules/configuration/components/CompanyForm.vue`
- `front-vue/src/modules/profile/composables/usePasswordPolicy.js` (nuevo)
- `front-vue/src/modules/profile/components/SecurityTab.vue`
- Formulario de usuarios (`front-vue/src/pages/users/`) donde se captura la contraseña
- Handlers MSW del módulo de perfil
- `front-vue/src/utils/api.js` (verificar manejo del 429 sin cambios de contrato)
**Descripción:**
1. `CompanyForm.vue`: `accept="image/png,image/jpeg,image/webp"` y texto de ayuda sin SVG.
2. `usePasswordPolicy()`: pide `GET /v1/profile/password-policy` una vez y la cachea; si falla usa una política local de 12 caracteres.
3. Mostrar `hint` bajo los campos de contraseña (`label` con ` *`, `placeholder` con ejemplo, `VTextField type="password"`); errores 422 inline con `useApiFormErrors`, sin toast (`frontend-ui.md`).
4. Mensaje claro en 429 de login con `useConfirm().alert({ message })`, sin diálogos propios.
5. Handler MSW de la política.
**Tests:**
- Unit de `usePasswordPolicy` (éxito, fallo de red → política local).
- `uiGuardrails.spec.js` y `routeMeta.spec.js` siguen en verde sin excepciones nuevas.
**Criterios de done:**
- [ ] Subir un SVG desde el selector ya no está permitido en la UI.
- [ ] Frontend: `cd front-vue && npm run lint` y `npm run test` (si existe) en verde.

### T-12 — Auditoría de dependencias y CI (H-13)
**Estado:** 🔧 · **Subj:** infra · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `Makefile` (target `audit`)
- `.github/workflows/security-audit.yml` (nuevo)
- `.github/dependabot.yml` (nuevo)
**Descripción:**
1. Target `audit`: `composer audit --no-dev` en el contenedor del backend y `npm audit --omit=dev --audit-level=high` en `front-vue`; falla con código ≠ 0 en severidad alta/crítica.
2. Workflow en `pull_request` y semanal (lunes 06:00 UTC) con jobs `composer-audit` y `npm-audit`.
3. Dependabot semanal para `composer` (`/back-laravel`), `npm` (`/front-vue` y `/`), `github-actions`.
4. Si el spec 006 definió CI en otra plataforma, mover los jobs allí y documentarlo (Open Question).
**Tests:**
- Ejecutar `make audit` localmente; registrar los hallazgos actuales en el PR (no se corrigen vulnerabilidades de terceros dentro de este spec; se abren tareas aparte).
**Criterios de done:**
- [ ] `make audit` funciona de punta a punta y el workflow es sintácticamente válido.

### T-13 — Pruebas transversales de regresión de seguridad (incluye US-11)
**Estado:** 🔧 · **Subj:** back · **Complejidad:** M · **Depende de:** T-1 … T-9
**Cubre:** US-11 (guardia de rutas, decisión D-29 de `design.md` §11).
**Archivos clave:**
- `back-laravel/tests/Feature/Security/` (todos los tests anteriores agrupados)
- `back-laravel/tests/Feature/Security/RouteAuthorizationGuardTest.php` (nuevo)
**Descripción:**
1. `RouteAuthorizationGuardTest`: recorrer `Route::getRoutes()` y fallar si una ruta bajo `api/v1` no tiene middleware `permission:*` y no está en la lista blanca (ver paso 2). Protege la regla de `api-authorization.md` ante futuras rutas.
2. Prueba de arquitectura (grep) de que no existe `default-fallback-jwt-secret` en `app/` y `config/`.
   - La lista blanca del paso 1 se escribe ruta por ruta (no por patrón) y contiene: todos los `/options`, `/company/summary`, `/profile*`, y las cinco rutas pendientes de decisión (`GET /categories/tree`, `GET /financial-accounts/tree`, `GET /ubigeo`, `POST /units/convert`, `GET /exchange-rates/current`). Si la decisión de requirements §7 asigna permiso a alguna, se quita de la lista y se le agrega el middleware en `routes/api.php`.
3. Revisar que el 403 por acción protegida de las suites de módulos sigue presente.
**Tests:**
- La suite completa: `make test` (aislado, `down -v`).
**Criterios de done:**
- [ ] `make test` completo en verde.
- [ ] `cd back-laravel && vendor/bin/pint --test` y Larastan (`phpstan.neon`) sin errores nuevos.

### T-14 — Reglas, documentación y cierre
**Estado:** 🔧 · **Subj:** docs · **Complejidad:** S · **Depende de:** T-1 … T-13
**Archivos clave:**
- `.claude/specs/035-security-owasp-hardening/CLAUDE.md.delta.md`
- `.agents/rules/security.md` (nuevo), `.agents/rules/auth-session.md`, `.agents/rules/file-uploads.md`, `.agents/rules/business-parameters.md`
- `README.md` (sección Seguridad / variables de entorno), runbook de despliegue
- `.claude/specs/README.md` (estado del spec)
**Descripción:**
1. Mergear el delta en las reglas (cada sección indica su destino).
2. README: variables obligatorias de producción (`APP_KEY`, `JWT_SECRET`, `CORS_ALLOWED_ORIGINS`), cómo generarlas, y los comandos `make audit` y `php artisan company:check-logo`.
3. Runbook: orden de despliegue (secretos → backend → nginx en `Report-Only` → verificación → enforcing), rotación de `APP_KEY` si el valor histórico se usó, y cómo revisar `storage/logs/security-*.log`.
4. Marcar el spec como `Done` en el índice y ejecutar `graphify update .`.
**Criterios de done:**
- [ ] Reglas mergeadas; el índice del spec actualizado.
- [ ] `graphify update .` ejecutado.

### T-15 — Promover la CSP a enforcing ⚪
**Estado:** ⚪ · **Subj:** infra · **Complejidad:** S · **Depende de:** T-10 y una ventana de observación sin violaciones
**Archivos clave:**
- `docker/nginx/snippets/security-headers.conf`
**Descripción:**
1. Tras observar el entorno en `Report-Only` sin violaciones (recorrido completo, incluidos PDFs y reportes), cambiar la directiva a `Content-Security-Policy`.
2. Si hay violaciones legítimas, corregir el origen (build de Vite o recurso externo) en lugar de relajar `script-src`.
**Criterios de done:**
- [ ] `curl -sI` muestra `Content-Security-Policy` (no `-Report-Only`) y la SPA funciona completa.
---
