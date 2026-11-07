# 035 · security-owasp-hardening — Delta de reglas
> **Instrucciones:** este archivo contiene **solo las reglas duras nuevas** que este spec agrega al repo. Al cerrar el spec se mergean en [.agents/rules/](../../../.agents/rules/) (archivo existente o uno nuevo con frontmatter `trigger` / `description`), según el destino indicado en cada sección.
>
> **Origen:** [requirements.md](./requirements.md), [design.md](./design.md), [todo.md](./todo.md).
>
---
## (+ en ".agents/rules/security.md" — archivo nuevo) Cabeceras de seguridad y configuración

- Toda respuesta pasa por `App\Http\Middleware\SecurityHeaders`: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`, y `Content-Security-Policy: default-src 'none'; frame-ancestors 'none'` en las respuestas de API. `X-XSS-Protection` está prohibido con valor `1; mode=block`. `Strict-Transport-Security` solo se emite bajo HTTPS y fuera de `local`/`testing`.
- La CSP de la SPA se declara solo en nginx (`docker/nginx/snippets/security-headers.conf`). `script-src` es `'self'` sin `'unsafe-inline'` ni `'unsafe-eval'`; relajar `script-src` para silenciar una violación está prohibido (se corrige el build o el recurso). Una CSP nueva o modificada pasa primero por `Content-Security-Policy-Report-Only`.
- Toda cabecera de seguridad de nginx vive en el snippet `security-headers.conf` y se incluye en el `server` y en cada `location` que declare `add_header` (nginx no hereda `add_header` hacia un `location` que define el suyo).
- `server_tokens off`, `fastcgi_hide_header X-Powered-By` y `expose_php = Off` (en `docker/php/php.prod.ini`) son obligatorios en producción; ninguna respuesta revela la versión de nginx ni de PHP.
- Ningún secreto (`APP_KEY`, `JWT_SECRET`, contraseñas) tiene valor por defecto en código, `config/*.php` ni archivos commiteados (`.env.example` deja esas claves vacías). `docker-compose.prod.yml` declara `JWT_SECRET` y `CORS_ALLOWED_ORIGINS` con `${VAR:?mensaje}`. Los compose de desarrollo y test pueden usar un secreto con prefijo `dev-only-`, que `JwtService` rechaza en producción.
- `back-laravel/.env.example` e `.env.example` de la raíz mantienen `APP_DEBUG=false` y `LOG_LEVEL=info`; el modo debug se activa a mano en el `.env` local.
- Toda consulta cruda (`whereRaw`, `selectRaw`, `orderByRaw`, `DB::raw`) usa bindings para cualquier valor variable; un fragmento SQL interpolado solo puede ser una constante `private const` de la clase, con comentario que prohíbe interpolar entrada de usuario.
- `composer audit` y `npm audit --omit=dev` (`make audit`) corren en cada PR y semanalmente en CI; una vulnerabilidad alta o crítica no se ignora sin una tarea registrada. Dependabot mantiene `composer`, `npm` y `github-actions`.

## (+ en ".agents/rules/security.md") Eventos de seguridad y logs

- Los eventos de autenticación, autorización y abuso se registran **solo** con `App\Support\Security\SecurityLogger` en el canal `security` (`storage/logs/security.log`, JSON, retención 90 días). Prohibido `Log::info()`/`Log::warning()` con datos de login o tokens fuera de ese componente.
- El `event` es un slug estable en `snake_case` (`login_failed`, `login_throttled`, `login_succeeded`, `refresh_rejected`, `permission_denied`, `rate_limited`, `password_changed`, `sessions_revoked`). Cada evento lleva `request_id`, `ip`, `user_id` (si existe) y `user_agent` truncado.
- Prohibido registrar contraseñas, tokens (access, refresh, CSRF), cookies, cabecera `Authorization` o cuerpos de petición. `SecurityLogger::sanitize()` descarta esas claves y elimina `\r`, `\n` y caracteres de control; los emails se registran enmascarados.
- Los servicios de dominio no leen `Request` para loguear; el controller o el handler de excepciones llama a `SecurityLogger` con los datos que el servicio devuelve (compatible con `multi-branch.md`: los servicios funcionan en jobs y scheduler).
- `X-Request-Id` entrante solo se acepta si cumple `^[A-Za-z0-9-]{8,64}$`; en otro caso `EnsureRequestId` genera un UUID. El canal `security` complementa, no reemplaza, el activity log de dominio (`activity-log.md`).

## (+ en ".agents/rules/security.md") CORS

- `config/cors.php` lee los orígenes de `CORS_ALLOWED_ORIGINS` (CSV). Los patrones de `localhost` solo se activan con `APP_ENV` `local` o `testing`. Prohibido `*` en `allowed_origins` mientras `supports_credentials` sea `true`.
- `allowed_headers` enumera las cabeceras realmente usadas (`Authorization`, `Content-Type`, `Accept`, `X-CSRF-TOKEN`, `X-Branch-Id`, `X-Request-Id`, `X-Refresh-Token`, `X-Requested-With`); una cabecera nueva del frontend se agrega ahí en el mismo PR.

## (+ en ".agents/rules/auth-session.md") Secreto JWT, login y límites

- `JwtService` exige un `JWT_SECRET` propio de al menos 32 bytes y lanza `RuntimeException` si falta o es más corto. Está prohibido un fallback a `APP_KEY` o a una cadena literal. En producción el secreto se valida al arrancar la aplicación.
- El login ejecuta siempre `Hash::check()` (contra un hash ficticio de igual costo cuando el email no existe) y responde con el mismo mensaje `Credenciales incorrectas.`; el estado "usuario inactivo" solo se informa tras una contraseña válida.
- El limitador `login` tiene tres ventanas: 5/min por `email|ip`, 15/hora por `email` y 30/min por `ip`. `POST /auth/refresh` y `POST /auth/logout` llevan `throttle:auth-refresh` y `throttle:auth-logout` (30/min por IP, ajustables en `config/security.php`). Toda ruta nueva de `/auth/*` declara su propio `throttle`.
- Los códigos de respuesta del refresh no cambian por este spec: CSRF que no coincide = 403 sin borrar cookies; token inválido, expirado o revocado = 401 con cookies eliminadas.
- Riesgo aceptado y vigente: el JWT lleva `email`, `name`, `roles` y `permissions` y la cookie `csrf_token` es legible por JS. La autorización real se evalúa contra la BD (Spatie), nunca contra los claims.

## (+ en ".agents/rules/auth-session.md") Política de contraseñas

- Toda contraseña que se fije o cambie (alta de usuario, edición, cambio de perfil, restablecimiento) valida con `App\Support\Auth\PasswordPolicy::rules()`; prohibido `min:N` suelto o reglas de contraseña propias en un FormRequest.
- Los umbrales viven en `system_parameters` (grupo `auth`): `auth.password_min_length` (12), `auth.password_require_mixed_case` (true), `auth.password_require_number` (true), `auth.password_check_pwned` (false). Se leen con `SystemParameterService`, nunca como constantes.
- El chequeo `uncompromised()` es opcional y **no bloquea** si el servicio externo no responde (`SafeUncompromised` loguea una advertencia). Un cambio de política nunca invalida contraseñas existentes ni cierra sesiones.
- El frontend muestra la política obtenida de `GET /api/v1/profile/password-policy` mediante `usePasswordPolicy()`; el backend es la fuente de verdad y los 422 se muestran inline sin toast.

## (+ en ".agents/rules/file-uploads.md") Archivos que se sirven desde el origen de la aplicación

- Está prohibido aceptar formatos que puedan contener scripts (`svg`, `html`, `xml` con XSLT) en el disco `public`. El logo de empresa acepta solo `png`, `jpg`, `jpeg` y `webp`, validados con `mimes` **y** `mimetypes` (tipo real) además de `max`.
- Todo archivo del disco `public` se sirve con `X-Content-Type-Options: nosniff` y una CSP restrictiva con `sandbox` en la ubicación `/storage` de nginx, de modo que un archivo activo ya existente no ejecute scripts.
- `php artisan company:check-logo` informa si el logo guardado es SVG; el administrador lo reemplaza manualmente (el comando no borra archivos).

## (+ en ".agents/rules/api-authorization.md") Guardia automática de rutas

- `Tests\Feature\Security\RouteAuthorizationGuardTest` recorre `Route::getRoutes()` y falla si una ruta bajo `/api/v1` no declara `permission:*` y no figura en la lista blanca documentada (los `/options`, `/company/summary`, `/profile*` y las rutas de lectura que la decisión de `requirements.md` §7 acepte como excepción; hoy pendientes: `/categories/tree`, `/financial-accounts/tree`, `/ubigeo`, `/units/convert`, `/exchange-rates/current`). La regla del archivo se amplía con la lista final de excepciones al cerrar el spec. Ampliar la lista blanca exige justificarlo en el PR; no se agregan excepciones *ad hoc*.

## (+ en ".agents/rules/business-parameters.md") Grupo `auth`

- El grupo `auth` agrupa los parámetros de seguridad de autenticación (`auth.password_*`). Se registran en `SystemParameterSeeder` con las mismas reglas que el resto de parámetros (`updateOrCreate` por `(group_key, item_key)`, `value_type` explícito, sin pisar `value` existente).
