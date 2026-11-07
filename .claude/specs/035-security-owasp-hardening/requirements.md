# 035 · security-owasp-hardening — Requirements
> **Spec:** 035 · **Alcance:** fullstack · **Módulo:** Transversal (Auth + infraestructura) · **Prioridad:** alta · **Depende de specs:** 005, 007, 009, 033
> **Status:** Draft
> **Última actualización:** 2026-10-29
> **Autores:** Luis Acuña Guevara
> **Origen:** Auditoría de seguridad estática del 2026-10-29 contra OWASP Top 10 / ASVS (cabeceras HTTP, autenticación y sesiones, control de acceso, validación de entrada, CORS, rate limiting, errores y logs)
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/) — reglas duras del repo (`auth-session.md`, `api-authorization.md`, `file-uploads.md`, `business-parameters.md`, `testing.md`).
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints de la API REST.
> - Specs previos relacionados: [005 api-resilience-and-exceptions](../005-api-resilience-and-exceptions/requirements.md), [007 audit-trail](../007-audit-trail/requirements.md), [009 user-profile-and-sessions](../009-user-profile-and-sessions/requirements.md), [033 session-tracking-and-audit-hardening](../033-session-tracking-and-audit-hardening/requirements.md).
>
---
## 1. Contexto de Negocio

> **Verificación posterior (2026-10-29):** se contrastaron los supuestos del spec con el código. Correcciones aplicadas: el permiso del logo es `configuration.update` (no `company.update`); el literal del JWT también está en `docker-compose.yml` (3 servicios) y `README.md`, y `docker-compose.test.yml` no define `JWT_SECRET`; PHP usa el `docker/php/php.prod.ini` existente; el `Makefile` no tiene target de setup; y la lista de rutas sin `permission:*` se completó (cinco rutas de lectura no exceptuadas por `api-authorization.md`, ver US-11 y §7).

El sistema maneja dinero (cajas, bancos, cobros, pagos), inventario y datos de clientes y proveedores. Una auditoría estática del código del 2026-10-29 contrastó la implementación contra el checklist OWASP (cabeceras de seguridad, autenticación/sesiones, control de acceso, validación de entrada, API/CORS/rate limiting, errores y logs). El resultado fue **15 hallazgos**: 3 altos, 6 medios y 6 bajos. Este spec los corrige.

**Lo que ya cumple (no se toca):** todas las rutas de negocio llevan `permission:*` y los ids usan `whereUuid` (A01); el access token vive solo en memoria y el refresh token es HttpOnly, hasheado, rotado y con CSRF (A07); las consultas `*Raw` usan bindings (A03); los adjuntos sensibles van al disco `local` con descarga autenticada; el manejador de errores devuelve mensajes genéricos con `request_id` en producción.

**Estado actual con código real (los hallazgos):**

| ID | Sev. | OWASP | Hallazgo | Evidencia |
|---|---|---|---|---|
| H-01 | Alta | A05 | Faltan `Content-Security-Policy` y `Strict-Transport-Security`; el nginx de producción no agrega ninguna cabecera | `back-laravel/app/Http/Middleware/SecurityHeaders.php`, `docker/nginx/default.prod.conf` |
| H-02 | Alta | A04/A07 | Secreto JWT por defecto conocido: fallback `default-fallback-jwt-secret-key-at-least-32-bytes!` en `JwtService` y `config/jwt.php`, y el mismo valor en `.env.example`. Si en producción faltan `JWT_SECRET` y `APP_KEY`, cualquiera forja tokens con cualquier rol. `docker-compose.prod.yml` exige `APP_KEY` pero no `JWT_SECRET` | `JwtService.php:35-45`, `config/jwt.php:4`, `.env.example:70` |
| H-03 | Alta | A05/A03 | Se aceptan logos **SVG** en el disco `public`; un SVG con `<script>` servido desde `/storage` en el mismo origen es XSS almacenado | `CompanyLogoRequest.php:25`, `CompanySettingService.php:62`, `CompanyForm.vue:256` |
| H-04 | Media | A07 | `POST /auth/refresh` y `POST /auth/logout` sin `throttle` | `routes/api.php:76-77` |
| H-05 | Media | A07 | Login: límite solo por `email\|ip` (rotar IP evade el límite); si el usuario no existe se omite `Hash::check` (enumeración por tiempo de respuesta) | `AuthController.php:29-41`, `AppServiceProvider.php:32` |
| H-06 | Media | A07 | Política de contraseña solo `min:8` | `UserRequest.php:43`, `UpdatePasswordRequest.php:30` |
| H-07 | Media | A05 | CORS con `supports_credentials: true`, regex que acepta cualquier `*.localhost` y orígenes fijos `http://`, sin variante por entorno | `config/cors.php` |
| H-08 | Media | A09 | No se registran eventos de seguridad (login fallido, refresh rechazado, 403, 429, revocación de sesiones); `LOG_LEVEL=debug` por defecto | `app/Modules/Auth` |
| H-09 | Media | A09 | `EnsureRequestId` acepta `X-Request-Id` del cliente sin validar formato ni largo (log injection / envenenamiento de trazabilidad) | `EnsureRequestId.php:20-28` |
| H-10 | Baja (aceptado) | A02 | El JWT incluye `email`, `name`, `roles` y `permissions`; el CSRF va en cookie legible por JS | `JwtService::issueAccessToken` — decisión documentada en `auth-session.md`; **solo se documenta el riesgo aceptado** |
| H-11 | Baja | A05 | `X-XSS-Protection: 1; mode=block` obsoleto; `X-Frame-Options: SAMEORIGIN` en lugar de `DENY` | `SecurityHeaders.php:23-24` |
| H-12 | Baja | A05 | Sin `server_tokens off` ni `expose_php=Off` (se filtra versión de nginx y PHP) | `docker/` |
| H-13 | Baja | A06 | Sin `composer audit` / `npm audit` ni Dependabot | `.github` ausente |
| H-14 | Baja | A05 | `.env.example` trae `APP_DEBUG=true` y una `APP_KEY` real commiteada | `back-laravel/.env.example:3-4` |
| H-15 | Baja | A03 | `selectRaw` con `$days` interpolado (constante interna, no explotable hoy) | `AccountsPayableQueryService.php:135-138` |

## 2. Actores

| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Usuario autenticado | Cualquier usuario del sistema | JWT Bearer + cookie `refresh_token` HttpOnly + `csrf_token` |
| Administrador de configuración | Rol con `configuration.update` | Sube el logo de la empresa |
| Administrador de usuarios | Rol con `users.create` / `users.update` | Define contraseñas de otros usuarios |
| Atacante externo no autenticado | — | Sin credenciales; ataca `/auth/*`, `/storage`, cabeceras, CORS |
| Operador de infraestructura / DevOps | Quien despliega con `docker-compose.prod.yml` | Define variables de entorno y nginx |

## 3. User Stories (formato EARS)

### US-1 — Cabeceras de seguridad completas
**Como** operador **quiero** que toda respuesta lleve las cabeceras de seguridad recomendadas **para** mitigar XSS, clickjacking, sniffing y downgrade a HTTP.
**Cuando** el navegador solicita la SPA, un asset estático o un endpoint de la API, **el sistema debe** responder con `Content-Security-Policy`, `Strict-Transport-Security` (solo bajo HTTPS), `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy` y sin `Server`/`X-Powered-By` con versión.
**Módulo:** Transversal (`SecurityHeaders` middleware + `docker/nginx/default.prod.conf` + `docker/php`)
**Permisos:** público
**Criterios de aceptación:**
- [ ] Toda respuesta de `/api/*` incluye `Content-Security-Policy: default-src 'none'; frame-ancestors 'none'` y `X-Frame-Options: DENY`.
- [ ] Toda respuesta de la SPA y de assets estáticos en producción incluye una CSP con `default-src 'self'`, `script-src 'self'`, `object-src 'none'`, `frame-ancestors 'none'`, `base-uri 'self'`, `form-action 'self'`.
- [ ] `Strict-Transport-Security: max-age=63072000; includeSubDomains; preload` solo se emite cuando la petición llegó por HTTPS (directo o por `X-Forwarded-Proto`), nunca en entornos locales HTTP.
- [ ] `X-XSS-Protection` ya no se envía como `1; mode=block` (se elimina o vale `0`).
- [ ] Las cabeceras siguen presentes en las ubicaciones nginx que declaran su propio `add_header` (assets estáticos, `/storage`).
- [ ] La CSP se despliega primero en modo `Report-Only` y se promueve a enforcing tras verificar que la SPA no tiene violaciones.
- [ ] `curl -I` en producción no muestra `Server: nginx/x.y.z` ni `X-Powered-By: PHP/x.y.z`.

### US-2 — Secreto JWT obligatorio y sin valores por defecto
**Como** operador **quiero** que la aplicación se niegue a arrancar sin un secreto JWT fuerte **para** que nunca se firmen tokens con un valor público.
**Cuando** la aplicación resuelve `config('jwt.secret')` y el valor falta o mide menos de 32 bytes, **el sistema debe** lanzar un error de configuración explícito (fail-closed) en lugar de usar un fallback.
**Módulo:** Auth (`/api/auth/*`)
**Permisos:** n/a (configuración)
**Criterios de aceptación:**
- [ ] No existe en el código ninguna cadena literal usada como secreto de respaldo (`default-fallback-jwt-secret-key-...` eliminada de `JwtService`, `config/jwt.php` y `.env.example`).
- [ ] `JwtService::__construct()` lanza `RuntimeException` con mensaje en español si el secreto resuelto mide menos de 32 bytes.
- [ ] En `APP_ENV=production` no se permite que `JWT_SECRET` coincida con `APP_KEY` por omisión silenciosa: `JWT_SECRET` es obligatorio (`${JWT_SECRET:?...}` en `docker-compose.prod.yml` para los 3 servicios).
- [ ] `.env.example` deja `APP_KEY=` y `JWT_SECRET=` vacíos con un comentario de cómo generarlos; un target nuevo `make secrets` imprime valores aleatorios (hoy el `Makefile` no tiene target de setup).
- [ ] `docker-compose.yml` ya no contiene el literal `default-fallback-jwt-secret-…` (hoy aparece en 3 servicios): usa un secreto de desarrollo `dev-only-…`, que `JwtService` rechaza si `APP_ENV=production`. `docker-compose.test.yml` hoy no define `JWT_SECRET`; se le agrega.
- [ ] El entorno de tests define un `JWT_SECRET` de prueba (≥ 32 bytes) en `phpunit.xml` y en `docker-compose.test.yml`; la suite completa sigue en verde.
- [ ] Documentado: si la `APP_KEY` del `.env.example` histórico se usó en algún entorno real, debe rotarse (invalida cookies/tokens: aceptable).

### US-3 — Logo de empresa sin vectores de script
**Como** administrador de configuración **quiero** subir el logo de la empresa **para** que aparezca en documentos y cabeceras, sin abrir un vector de XSS almacenado.
**Cuando** se sube un archivo a `POST /api/v1/company/logo`, **el sistema debe** aceptar solo PNG, JPG/JPEG y WEBP, verificando el tipo MIME real del contenido.
**Módulo:** Configuration (`/api/v1/company/logo`)
**Permisos:** `configuration.update`
**Criterios de aceptación:**
- [ ] `CompanyLogoRequest` rechaza `svg` (y cualquier otro formato) con 422 y mensaje en español; valida con `mimes:png,jpg,jpeg,webp` **y** `mimetypes:image/png,image/jpeg,image/webp`.
- [ ] `CompanyForm.vue` ya no anuncia ni acepta SVG (`accept`, texto de ayuda).
- [ ] El nginx de producción sirve `/storage` con `X-Content-Type-Options: nosniff` y `Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox`, de modo que un SVG ya existente no ejecute scripts.
- [ ] Existe un comando `php artisan company:check-logo` que informa (sin borrar) si el `logo_path` guardado es SVG, para que el administrador lo reemplace.
- [ ] Test: subir un `.svg` con `<script>` responde 422; subir un PNG válido responde 200.

### US-4 — Límite de peticiones en refresh y logout
**Como** operador **quiero** limitar `POST /auth/refresh` y `POST /auth/logout` **para** impedir fuerza bruta y abuso sobre tokens y CSRF.
**Cuando** una misma IP supera el límite configurado, **el sistema debe** responder 429 con el mensaje estándar y `Retry-After`.
**Módulo:** Auth (`/api/auth/refresh`, `/api/auth/logout`)
**Permisos:** público (autenticación por cookie)
**Criterios de aceptación:**
- [ ] `RateLimiter::for('auth-refresh')` y `RateLimiter::for('auth-logout')` definidos en `AppServiceProvider` y aplicados con `->middleware('throttle:…')`.
- [ ] Límite holgado para no romper el uso multi-pestaña (el refresh se serializa con `withRefreshLock()`): 30/min por IP para refresh, 30/min por IP para logout.
- [ ] Se **conserva** el comportamiento de `auth-session.md`: CSRF que no coincide responde 403 sin borrar cookies; token inválido/expirado/revocado responde 401 y elimina cookies. La diferencia 401/403 es contractual y no se cambia.
- [ ] Test: la petición 31 en un minuto responde 429; una pestaña normal con refresh silencioso no se ve afectada.

### US-5 — Login resistente a fuerza bruta y enumeración
**Como** operador **quiero** que el login limite intentos por cuenta y por IP y tarde lo mismo exista o no el usuario **para** dificultar la fuerza bruta distribuida y la enumeración de usuarios.
**Cuando** se envía `POST /auth/login`, **el sistema debe** ejecutar siempre una verificación de hash en tiempo comparable y aplicar tres límites: `email|ip`, solo `email`, solo `ip`.
**Módulo:** Auth (`/api/auth/login`)
**Permisos:** público
**Criterios de aceptación:**
- [ ] Si el email no existe, se ejecuta `Hash::check()` contra un hash ficticio precalculado (mismo costo bcrypt) antes de responder `Credenciales incorrectas.`.
- [ ] El limitador `login` devuelve tres `Limit`: 5/min por `email|ip`, 15/hora por `email` (cualquier IP) y 30/min por `ip`.
- [ ] El estado "usuario inactivo" solo se informa **después** de verificar la contraseña correcta (ya ocurre); no se agrega un mensaje distinto antes.
- [ ] Todo 429 de login se registra en el canal `security` (US-8).
- [ ] Test: 16 intentos fallidos con IPs distintas contra el mismo email producen 429; el tiempo de respuesta con usuario inexistente es del mismo orden que con usuario existente.

### US-6 — Política de contraseñas robusta y configurable
**Como** administrador **quiero** una política de contraseñas fuerte y ajustable **para** reducir el riesgo de credenciales débiles.
**Cuando** se crea un usuario, se restablece su contraseña o el usuario cambia la suya, **el sistema debe** validar longitud mínima, mezcla de caracteres y (opcionalmente) que no esté en filtraciones públicas.
**Módulo:** Users + Profile (`/api/v1/users`, `/api/v1/profile/password`)
**Permisos:** `users.create`/`users.update`, autenticado (perfil)
**Criterios de aceptación:**
- [ ] Una única regla centralizada `PasswordPolicy` (usada por `UserRequest` y `UpdatePasswordRequest`); prohibido repetir `min:8` suelto.
- [ ] Parámetros en `system_parameters` (regla `business-parameters.md`): `auth.password_min_length` (defecto 12), `auth.password_require_mixed_case` (true), `auth.password_require_number` (true), `auth.password_check_pwned` (false por defecto).
- [ ] Con `auth.password_check_pwned=true` se usa `Password::uncompromised()`; si el servicio externo no responde, la validación **no bloquea** (falla abierta solo para este chequeo) y se registra una advertencia.
- [ ] Las contraseñas existentes siguen funcionando; la política solo aplica al fijar/cambiar una contraseña.
- [ ] Los mensajes de error son en español y los formularios del frontend muestran la política como texto de ayuda.
- [ ] Test: contraseñas de 8 caracteres o sin mayúscula/número se rechazan con 422.

### US-7 — CORS estricto por entorno
**Como** operador **quiero** que los orígenes permitidos vengan de configuración por entorno **para** que producción nunca acepte orígenes de desarrollo.
**Cuando** un navegador envía una petición cross-origin con credenciales, **el sistema debe** aceptarla solo si el origen figura en `CORS_ALLOWED_ORIGINS`.
**Módulo:** Transversal (`config/cors.php`)
**Permisos:** público
**Criterios de aceptación:**
- [ ] `allowed_origins` se lee de `CORS_ALLOWED_ORIGINS` (lista separada por comas); `allowed_origins_patterns` queda vacío salvo `APP_ENV` `local`/`testing`.
- [ ] `.env.example` define los orígenes de desarrollo; `docker-compose.prod.yml` exige `CORS_ALLOWED_ORIGINS` sin valor por defecto.
- [ ] Nunca se combina `*` con `supports_credentials: true`.
- [ ] `allowed_headers` enumera las cabeceras realmente usadas (`Authorization`, `Content-Type`, `Accept`, `X-CSRF-TOKEN`, `X-Branch-Id`, `X-Request-Id`, `X-Refresh-Token`) en lugar de `*`.
- [ ] Test: preflight desde un origen no listado no recibe `Access-Control-Allow-Origin`; desde uno listado sí.

### US-8 — Registro de eventos de seguridad
**Como** responsable de seguridad **quiero** un registro dedicado de eventos de seguridad **para** detectar ataques y reconstruir incidentes.
**Cuando** ocurre un login fallido, un login bloqueado por límite, un refresh rechazado, una respuesta 403/429, un cambio de contraseña o una revocación de sesiones, **el sistema debe** registrar un evento estructurado en el canal `security`.
**Módulo:** Transversal (`App\Support\Security\SecurityLogger`)
**Permisos:** n/a (registro interno)
**Criterios de aceptación:**
- [ ] Canal `security` en `config/logging.php` (driver `daily`, archivo propio, retención 90 días, nivel `info`).
- [ ] Eventos con `event` en `snake_case` estable: `login_failed`, `login_throttled`, `login_succeeded`, `refresh_rejected`, `permission_denied`, `rate_limited`, `password_changed`, `sessions_revoked`.
- [ ] Cada evento incluye `request_id`, `ip`, `user_id` (si existe), `user_agent` truncado a 200 y el motivo; el email se registra enmascarado (`ab***@dominio.com`).
- [ ] **Nunca** se registran contraseñas, tokens (access, refresh, CSRF), cookies ni cuerpos de petición.
- [ ] Todo valor de texto libre se sanea: se eliminan `\r`, `\n` y caracteres de control, y se trunca (anti log-injection).
- [ ] `LOG_LEVEL` por defecto de `.env.example` pasa a `info`.
- [ ] Test: tras un login fallido existe una línea `login_failed` sin la contraseña enviada; un valor con `\r\n` queda en una sola línea.

### US-9 — Identificador de petición confiable
**Como** operador **quiero** que `X-Request-Id` sea siempre válido **para** que la trazabilidad no pueda ser envenenada por el cliente.
**Cuando** llega un `X-Request-Id`, **el sistema debe** aceptarlo solo si cumple `^[A-Za-z0-9-]{8,64}$`; en otro caso genera un UUID nuevo.
**Módulo:** Transversal (`EnsureRequestId`)
**Permisos:** público
**Criterios de aceptación:**
- [ ] Un `X-Request-Id` con espacios, saltos de línea, caracteres especiales o más de 64 caracteres se reemplaza por un UUID.
- [ ] La respuesta devuelve el id efectivamente usado.
- [ ] Test unitario con valores válidos e inválidos.

### US-10 — Auditoría de dependencias y endurecimiento de configuración
**Como** equipo de desarrollo **quiero** un chequeo periódico de vulnerabilidades y plantillas de entorno seguras **para** detectar componentes vulnerables y evitar despliegues inseguros por copiar un `.env.example`.
**Cuando** se ejecuta `make audit` o corre el flujo programado de CI, **el sistema debe** reportar vulnerabilidades conocidas de Composer y npm.
**Módulo:** Transversal (CI / Makefile / `.env.example`)
**Permisos:** n/a
**Criterios de aceptación:**
- [ ] `make audit` ejecuta `composer audit` (en el contenedor de backend) y `npm audit --omit=dev` (frontend) y falla con código ≠ 0 ante vulnerabilidades de severidad alta o crítica.
- [ ] Existe un flujo de CI semanal y en cada PR que ejecuta lo anterior, más configuración de Dependabot para Composer, npm y GitHub Actions.
- [ ] `back-laravel/.env.example` queda con `APP_KEY=` vacío, `JWT_SECRET=` vacío, `APP_DEBUG=false` y comentarios que advierten no copiarlo a producción sin completarlo.
- [ ] La constante `$days` de `AccountsPayableQueryService::aging()` pasa a `private const` con comentario que prohíbe interpolar entrada de usuario en SQL crudo (H-15).
- [ ] El riesgo aceptado H-10 (claims en el JWT, cookie CSRF legible) queda documentado en `.agents/rules/auth-session.md`.

### US-11 — Guardia automática de autorización de rutas
**Como** equipo de desarrollo **quiero** un test que falle cuando una ruta de negocio se publique sin permiso **para** que la regla de `api-authorization.md` (control de acceso, OWASP A01 "deny by default") no dependa de la revisión manual.
**Cuando** se ejecuta la suite de tests, **el sistema debe** recorrer todas las rutas bajo `/api/v1` y fallar si alguna no declara `permission:*` y no figura en la lista blanca documentada.
**Módulo:** Transversal (`tests/Feature/Security/RouteAuthorizationGuardTest.php`)
**Permisos:** n/a (test)
**Criterios de aceptación:**
- [ ] La lista blanca refleja las rutas **verificadas** sin `permission:*` el 2026-10-29 (recorrido completo de `routes/api.php`, no solo una muestra): los `/options` (todos los módulos), `/company/summary`, el grupo `/profile*`, y cuatro rutas que `api-authorization.md` **no** exceptúa hoy: `GET /categories/tree`, `GET /financial-accounts/tree`, `GET /ubigeo`, `POST /units/convert` y `GET /exchange-rates/current`. Estas cinco requieren una decisión (ver §7); mientras tanto el test las admite como excepciones nombradas una por una, no por patrón.
- [ ] Una ruta nueva sin `permission:*` hace fallar el test con un mensaje que indica el método y la URI.
- [ ] Ampliar la lista blanca exige justificarlo en el PR; no se agregan excepciones ad hoc.
- [ ] Las rutas `/api/auth/*` (públicas por diseño) quedan fuera del recorrido, porque el test solo cubre el prefijo `api/v1`.
- [ ] Hoy el test pasa en verde sobre el código actual. El recorrido manual del 2026-10-29 no encontró **ninguna ruta de negocio** sin permiso fuera de la lista anterior; si el test detecta otra, se registra como hallazgo nuevo y no se silencia.

## 4. Reglas de negocio e invariantes
- Ninguna ruta de negocio pierde su middleware `permission:*` ni su caso de test 403 (`api-authorization.md`).
- El contrato 401/403 del refresh descrito en `auth-session.md` no cambia; solo se agrega el límite 429.
- La CSP de la SPA solo se activa en modo enforcing después de una fase `Report-Only` sin violaciones.
- Los eventos de seguridad (US-8) complementan, no reemplazan, el activity log del spec 007: el activity log audita cambios de datos de dominio; el canal `security` audita autenticación, autorización y abuso.
- Un cambio de política de contraseñas nunca invalida contraseñas existentes ni fuerza cierre de sesiones.
- Toda subida de archivo mantiene `mimes` + `max` en el FormRequest (`file-uploads.md`) y agrega verificación de tipo MIME real cuando el archivo va al disco `public`.
- Ningún secreto (JWT, APP_KEY, contraseñas de BD) tiene valor por defecto en código ni en archivos commiteados.

## 5. Requisitos no funcionales
- **Seguridad:** cubrir los 15 hallazgos de la sección 1; H-10 solo se documenta.
- **Compatibilidad:** el refresh silencioso multi-pestaña y el flujo de login existente no deben degradarse (límites holgados, sin cambios de contrato JSON).
- **Rendimiento:** el middleware `SecurityHeaders` y el sanitizador de logs son O(1) por petición; el hash ficticio del login se calcula una vez por proceso.
- **Operación:** los despliegues sin `JWT_SECRET` o `CORS_ALLOWED_ORIGINS` fallan al iniciar con un mensaje claro (mejor que arrancar inseguro).
- **Calidad:** `make test` y `vendor/bin/pint --test` en verde; `npm run lint` en verde para los cambios del front; cada cambio de comportamiento con test.

## 6. Out of Scope
- WAF, bloqueo por geolocalización o detección de anomalías (futuro).
- MFA / TOTP y gestión de contraseñas expiradas (spec propio si se requiere).
- Cifrado de columnas sensibles en base de datos.
- Migrar el JWT de HS256 a RS256/EdDSA (se evalúa en un spec futuro; el riesgo se documenta).
- Mover el CSRF a cookie HttpOnly (decisión vigente en `auth-session.md`).
- Escaneo antivirus de adjuntos.
- Pruebas de penetración dinámicas (DAST); este spec cubre el hallazgo de la revisión estática.

## 7. Open Questions
> Cada pregunta lleva su **supuesto provisional**: es lo que el spec asume mientras no haya respuesta, y lo que se implementa si nadie la contesta. Una respuesta distinta solo cambia la tarea indicada.

- [ ] ¿Producción termina TLS en nginx o detrás de un balanceador (Cloudflare, etc.)? Define si HSTS se emite desde nginx o desde Laravel con `TrustProxies` — responsable: DevOps.
  - **Supuesto provisional:** nginx emite HSTS y la variable `TRUSTED_PROXIES` queda sin definir. **Afecta:** T-4 y T-10. **Bloquea:** solo el despliegue de HSTS, no el resto.
- [ ] ¿Se activa `auth.password_check_pwned` (consulta externa a HaveIBeenPwned con k-anonimato)? — responsable: dueño del producto.
  - **Supuesto provisional:** apagado (`false`). **Afecta:** T-6 (solo el valor sembrado). **Bloquea:** nada; se cambia después sin código.
- [ ] ¿La plataforma de CI es GitHub Actions? El repo no tiene `.github/`; el spec 006 puede haber definido otra — responsable: líder técnico.
  - **Supuesto provisional:** GitHub Actions. **Afecta:** T-12. **Bloquea:** solo T-12; `make audit` funciona igual en local.
- [ ] ¿Existe algún logo SVG ya cargado en producción? `company:check-logo` lo detecta; si existe, reemplazarlo antes de promover la CSP de `/storage` — responsable: administrador de configuración.
  - **Supuesto provisional:** puede existir; la CSP con `sandbox` de `/storage` lo neutraliza igual. **Afecta:** T-8 y T-10. **Bloquea:** nada.
- [ ] ¿La `APP_KEY` del `.env.example` histórico se usó en algún entorno real? Si sí, rotarla en ese entorno — responsable: DevOps.
  - **Supuesto provisional:** sí se usó; la rotación queda como paso del runbook. **Afecta:** T-2 y T-14. **Costo de rotar:** invalida cookies y tokens vigentes (los usuarios vuelven a iniciar sesión).
- [ ] ¿Las cinco rutas de lectura sin permiso (`/categories/tree`, `/financial-accounts/tree`, `/ubigeo`, `/units/convert`, `/exchange-rates/current`) se aceptan como excepciones de `api-authorization.md` o se les asigna permiso? — responsable: líder técnico.
  - **Supuesto provisional:** se aceptan `/categories/tree`, `/ubigeo` y `/units/convert` (catálogos sin datos sensibles) y se agregan a la regla; `/financial-accounts/tree` y `/exchange-rates/current` son datos financieros: se les asigna el permiso de lectura de su módulo (`finance.*`, a confirmar) o se justifican por escrito. **Afecta:** T-13 y el delta de `api-authorization.md`. **Bloquea:** solo T-13.
- [ ] Retención de 90 días del canal `security`: ¿cumple la política de la empresa? — responsable: dueño del producto.
  - **Supuesto provisional:** 90 días, configurable con `SECURITY_LOG_DAYS`. **Afecta:** T-3. **Bloquea:** nada.

## 8. Métricas de éxito
- 15/15 hallazgos cerrados o documentados (H-10 documentado como riesgo aceptado).
- `curl -I` a la SPA y a la API en producción muestra todas las cabeceras de US-1 y ninguna versión de servidor.
- La aplicación no arranca (en producción y local) sin `JWT_SECRET` ≥ 32 bytes.
- 0 violaciones de CSP en consola durante un recorrido completo de la SPA antes de pasar a enforcing.
- Test automatizado de 429 en refresh, logout y login distribuido; test de rechazo de SVG; test de saneamiento de logs.
- `make audit` en verde en CI y Dependabot activo.
- `RouteAuthorizationGuardTest` en verde y activo en CI: ninguna ruta nueva de `/api/v1` llega a `master` sin `permission:*`.
- `make test` completo en verde y `vendor/bin/pint --test` sin diferencias.
