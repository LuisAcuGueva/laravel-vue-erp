# 035 · security-owasp-hardening — Design
> **Spec:** 035 · **Alcance:** fullstack · **Módulo:** Transversal (Auth + infraestructura) · **Prioridad:** alta · **Depende de specs:** 005, 007, 009, 033
> **Status:** Draft
> **Última actualización:** 2026-10-29
> **Autores:** Luis Acuña Guevara
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md) — define el qué/por qué; este documento define el cómo.
> **Reglas:** [.agents/rules/](../../../.agents/rules/) · **Makefile:** [Makefile](../../../Makefile)
>
---
## 0. Ubicación y alcance

No crea un módulo de dominio nuevo: endurece piezas transversales existentes.

| Capa | Ubicación | Cambio |
|---|---|---|
| Backend – middleware | `back-laravel/app/Http/Middleware/SecurityHeaders.php`, `EnsureRequestId.php` | Modificar |
| Backend – soporte | `back-laravel/app/Support/Security/SecurityLogger.php` | Nuevo |
| Backend – soporte | `back-laravel/app/Support/Auth/PasswordPolicy.php` | Nuevo |
| Backend – Auth | `app/Modules/Auth/Services/JwtService.php`, `Controllers/AuthController.php` | Modificar |
| Backend – Users/Profile | `app/Modules/Users/Requests/UserRequest.php`, `app/Modules/Profile/Requests/UpdatePasswordRequest.php`, `app/Modules/Profile/Services/ProfileService.php` | Modificar |
| Backend – Configuration | `app/Modules/Configuration/Requests/CompanyLogoRequest.php`, `Console/CheckCompanyLogoCommand.php` | Modificar / nuevo |
| Backend – Purchases | `app/Modules/Purchases/Services/AccountsPayableQueryService.php` | Modificar (constante) |
| Backend – config | `config/jwt.php`, `config/cors.php`, `config/logging.php`, `config/security.php` | Modificar / nuevo |
| Backend – bootstrap | `bootstrap/app.php`, `app/Providers/AppServiceProvider.php`, `routes/api.php` | Modificar |
| Backend – seed | `database/seeders/SystemParameterSeeder.php` | Modificar |
| Infra | `docker/nginx/default.prod.conf`, `docker/nginx/snippets/security-headers.conf`, `docker/nginx/Dockerfile.prod`, `docker/php/php.prod.ini`, `docker-compose.prod.yml`, `docker-compose.yml`, `Makefile` | Modificar / nuevo |
| CI | `.github/workflows/security-audit.yml`, `.github/dependabot.yml` | Nuevo |
| Frontend | `front-vue/src/modules/configuration/components/CompanyForm.vue`, formularios de contraseña (`modules/profile/components/SecurityTab.vue`, formulario de usuarios) | Modificar |
| Docs/Reglas | `.agents/rules/security.md` (nuevo), `.agents/rules/auth-session.md`, `.agents/rules/file-uploads.md` | Ver `CLAUDE.md.delta.md` |

Secciones sin tablas ni modelos nuevos: **No aplica** (§1, §6).

## 1. Backend — Modelo y Persistencia

**No aplica.** No se crean tablas ni modelos. Los parámetros de contraseña usan la tabla existente `system_parameters` (§2.4).

## 2. Backend — Lógica de Negocio y Servicios

### 2.1 SecurityHeaders (H-01, H-11)

```php
// app/Http/Middleware/SecurityHeaders.php
public function handle(Request $request, Closure $next): Response
{
    $response = $next($request);
    $h = $response->headers;

    $h->set('X-Content-Type-Options', 'nosniff');
    $h->set('X-Frame-Options', 'DENY');
    $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
    $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    $h->set('Cross-Origin-Opener-Policy', 'same-origin');
    $h->remove('X-XSS-Protection');           // obsoleto: nunca "1; mode=block"
    $h->remove('X-Powered-By');

    // La API devuelve JSON, nunca HTML: CSP máxima.
    if ($request->is('api/*') || $request->expectsJson()) {
        $h->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
    }

    // HSTS solo bajo HTTPS (directo o detrás de proxy de confianza) y nunca en local.
    if ($request->isSecure() && ! app()->environment(['local', 'testing'])) {
        $h->set('Strict-Transport-Security', config('security.hsts'));
    }

    return $response;
}
```

- `config/security.php`: `hsts` (`'max-age=63072000; includeSubDomains; preload'`), `csp.spa` (referencia, ver §7), `trusted_proxies`.
- `$request->isSecure()` depende de `TrustProxies`: en `bootstrap/app.php` se configura `$middleware->trustProxies(at: env('TRUSTED_PROXIES'), headers: Request::HEADER_X_FORWARDED_FOR | HEADER_X_FORWARDED_PROTO | …)` solo si el despliegue usa balanceador (Open Question). Si nginx termina TLS, el HSTS se emite en nginx (§7) y el de PHP es redundante pero inocuo.
- La CSP de la **SPA** no la emite Laravel (no sirve HTML); la emite nginx (§7).

### 2.2 JwtService fail-closed (H-02, H-14)

```php
public function __construct()
{
    $secret = (string) config('jwt.secret');

    if (strlen($secret) < 32) {
        throw new \RuntimeException(
            'JWT_SECRET no está configurado o mide menos de 32 bytes. Genere uno con: openssl rand -base64 48'
        );
    }
    // ... resto igual (sin fallback a APP_KEY ni a cadena literal)
}
```

- `config/jwt.php`: `'secret' => env('JWT_SECRET')` (sin `?:` ni literal). Se acepta también el prefijo `base64:` (se decodifica) para poder usar la salida de `openssl rand -base64 48`.
- Se **elimina** el fallback a `APP_KEY`: separar claves limita el impacto de una filtración.
- `JwtService` se resuelve en cada request autenticada; el fallo ocurre en el primer acceso, y además `AppServiceProvider::boot()` valida en producción al iniciar (`if (app()->isProduction()) app(JwtService::class);`) para fallar en el despliegue y no en el primer login.
- **Entornos dev y test:** hoy `docker-compose.yml` (3 servicios) declara `JWT_SECRET: ${JWT_SECRET:-default-fallback-jwt-secret-key-at-least-32-bytes!}` y `docker-compose.test.yml` **no define `JWT_SECRET`** (los tests dependen del fallback). Con el cambio fail-closed los tests fallarían. Se reemplaza el literal por `${JWT_SECRET:-dev-only-jwt-secret-not-for-production-0123456789}` en `docker-compose.yml` y se agrega la misma variable a `docker-compose.test.yml` y a `phpunit.xml` (`<env name="JWT_SECRET" value="…"/>`). `docker-compose.prod.yml` sigue sin valor por defecto. Además `JwtService` rechaza en `APP_ENV=production` cualquier secreto que empiece por `dev-only-`.
- El `README.md` también menciona el literal por defecto: actualizar la documentación.
- **Fuera de alcance, anotado:** `docker-compose.yml` y `docker-compose.test.yml` también traen un `APP_KEY` fijo por defecto (`base64:Ug+6…`). Es solo para desarrollo y `docker-compose.prod.yml` ya exige `APP_KEY` sin valor por defecto, así que no se cambia en este spec; si se quiere eliminar, es una tarea aparte.
- `.env.example`: `APP_KEY=`, `JWT_SECRET=`, `APP_DEBUG=false`, con comentarios. El `Makefile` **no tiene hoy un target de setup**: se crea `make secrets`, que imprime (sin escribir en ningún archivo) un `APP_KEY` y un `JWT_SECRET` aleatorios para pegarlos en el `.env` (comando sugerido: `php -r 'echo base64_encode(random_bytes(48));'`).

### 2.3 Login endurecido (H-05)

```php
// AppServiceProvider::boot()
RateLimiter::for('login', function (Request $request) {
    $email = Str::lower(trim((string) $request->input('email', '')));

    return [
        Limit::perMinute(5)->by('login:ei:'.$email.'|'.$request->ip()),
        Limit::perHour(15)->by('login:e:'.$email),          // fuerza bruta distribuida
        Limit::perMinute(30)->by('login:i:'.$request->ip()), // password spraying
    ];
});
```

```php
// AuthController::login()
private const DUMMY_HASH = null; // se calcula una vez por proceso

$user = User::where('email', $validated['email'])->first();
$hash = $user?->password ?? $this->dummyHash();   // Hash::make('x') memoizado en static
$valid = Hash::check($validated['password'], $hash);

if (! $user || ! $valid) {
    SecurityLogger::loginFailed($request, $validated['email'], $user?->id);
    return response()->json(['message' => 'Credenciales incorrectas.'], 401);
}
```

- `dummyHash()` usa `Hash::make(Str::random(32))` cacheado en una propiedad estática, con las mismas rondas que las contraseñas reales (`BCRYPT_ROUNDS`).
- El orden "contraseña válida → luego estado inactivo" se mantiene.
- El handler de `ThrottleRequestsException` (en `bootstrap/app.php`) llama a `SecurityLogger::rateLimited()` con el nombre del limitador cuando la ruta es de `auth/*`.

### 2.4 PasswordPolicy (H-06)

```php
// app/Support/Auth/PasswordPolicy.php
final class PasswordPolicy
{
    /** @return list<\Illuminate\Contracts\Validation\Rule|string|Password> */
    public static function rules(): array
    {
        $params = app(SystemParameterService::class);

        $rule = Password::min((int) $params->get('auth.password_min_length', 12));
        if ($params->get('auth.password_require_mixed_case', true)) { $rule->mixedCase(); }
        if ($params->get('auth.password_require_number', true)) { $rule->numbers(); }
        if ($params->get('auth.password_check_pwned', false)) { $rule->uncompromised(); }

        return ['string', $rule];
    }

    public static function hint(): string; // texto de ayuda en español para el frontend
}
```

- Registrar en `SystemParameterSeeder` (`updateOrCreate` por `(group_key, item_key)`, `value_type` explícito, sin pisar `value`): `auth.password_min_length` (integer, 12), `auth.password_require_mixed_case` (boolean, true), `auth.password_require_number` (boolean, true), `auth.password_check_pwned` (boolean, false). Grupo `auth` es nuevo; ver nota en `business-parameters.md` (delta).
- `uncompromised()` consulta una API externa: si falla la red, Laravel lanza excepción; `PasswordPolicy` la captura con una regla envoltorio `SafeUncompromised` que loguea `Log::warning` y **no bloquea** (fail-open solo para este chequeo, ver requirements US-6).
- `UserRequest`: `'password' => $isPost ? ['required', ...PasswordPolicy::rules()] : ['nullable', ...PasswordPolicy::rules()]`.
- `UpdatePasswordRequest`: `['required', 'confirmed', ...PasswordPolicy::rules()]` y mantiene `current_password`. Se agrega regla `different:current_password`.
- Mensajes de validación en español en `lang/es` o `messages()` de cada Request.
- Endpoint de solo lectura para el front: `GET /api/v1/profile/password-policy` (solo autenticación, sin datos sensibles, ver §4) devuelve `{ "data": { "min_length": 12, "mixed_case": true, "number": true, "hint": "..." } }`. Alternativa descartada en §10.

### 2.5 SecurityLogger (H-08)

```php
// app/Support/Security/SecurityLogger.php
final class SecurityLogger
{
    public static function loginFailed(Request $r, string $email, ?string $userId): void;
    public static function loginSucceeded(Request $r, User $u): void;
    public static function loginThrottled(Request $r, string $email): void;
    public static function refreshRejected(Request $r, string $reason, ?string $sessionId = null): void;
    public static function permissionDenied(Request $r): void;
    public static function rateLimited(Request $r, string $limiter): void;
    public static function passwordChanged(Request $r, User $u): void;
    public static function sessionsRevoked(Request $r, User $u, int $count): void;

    private static function log(string $event, Request $r, array $ctx = []): void
    {
        Log::channel('security')->info($event, self::sanitize([
            'event'      => $event,
            'request_id' => $r->header('X-Request-Id'),
            'ip'         => $r->ip(),
            'user_id'    => $r->user()?->id,
            'user_agent' => $r->userAgent(),
            ...$ctx,
        ]));
    }

    /** Quita \r \n y caracteres de control, trunca a 200, enmascara email y elimina claves prohibidas. */
    private static function sanitize(array $data): array;
}
```

- Lista negra de claves que `sanitize()` descarta siempre: `password`, `current_password`, `token`, `access_token`, `refresh_token`, `csrf_token`, `authorization`, `cookie`.
- Enmascarado de email: primeras 2 letras del usuario + `***` + `@dominio`.
- `config/logging.php`: canal `security` → `['driver' => 'daily', 'path' => storage_path('logs/security.log'), 'level' => 'info', 'days' => 90, 'replace_placeholders' => true]`. Formato JSON (`Monolog\Formatter\JsonFormatter`) para que una línea = un evento y no se pueda inyectar una línea falsa.
- Puntos de llamada: `AuthController::login/refresh/logout`, `JwtService::rotateRefreshToken` (motivos: `invalid`, `csrf_mismatch`, `expired`, `revoked`), `ProfileService` (cambio de contraseña, revocación de sesiones), `bootstrap/app.php` (handlers 403 y 429).
- **Servicios de dominio** no llaman a `SecurityLogger` leyendo `Request` (regla `multi-branch.md`: los servicios reciben parámetros); `JwtService::rotateRefreshToken` recibe el motivo y el controller/middleware loguea con la request.

### 2.6 EnsureRequestId (H-09)

```php
$requestId = $request->header('X-Request-Id');
if (! is_string($requestId) || ! preg_match('/^[A-Za-z0-9-]{8,64}$/', $requestId)) {
    $requestId = (string) Str::uuid();
    $request->headers->set('X-Request-Id', $requestId);
}
```

### 2.7 Logo (H-03)

- `CompanyLogoRequest::rules()`: `['required', 'file', 'mimes:png,jpg,jpeg,webp', 'mimetypes:image/png,image/jpeg,image/webp', 'max:2048']`. Mensajes en español.
- `CheckCompanyLogoCommand` (`company:check-logo`): lee el `logo_path` vía `CompanySettingService::current()` y avisa si termina en `.svg` o si el MIME real del archivo es `image/svg+xml`. Solo informa.
- `PdfRenderer` (`app/Support/Pdf/PdfRenderer.php:42`) lee el logo del disco `public`: ya no puede recibir SVG nuevos; sin cambios.

### 2.8 Constante SQL (H-15)

```php
/** Fragmento SQL fijo. Prohibido interpolar aquí entrada de usuario; solo bindings. */
private const DAYS_OVERDUE_SQL = 'CAST(? AS DATE) - pi.due_date';
```

Reemplaza la variable local `$days` en `aging()`.

## 3. Backend — Presentation (Controllers, Requests, Resources)

- Rutas en `routes/api.php`:

```php
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('throttle:auth-refresh');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('throttle:auth-logout');
    ...
});
```

- Limitadores nuevos en `AppServiceProvider::boot()`:

```php
RateLimiter::for('auth-refresh', fn (Request $r) => Limit::perMinute(30)->by('refresh:'.$r->ip()));
RateLimiter::for('auth-logout',  fn (Request $r) => Limit::perMinute(30)->by('logout:'.$r->ip()));
```

  El valor 30/min por IP cubre varias pestañas por usuario detrás de la misma IP (oficinas con NAT): si se observan 429 legítimos, subirlo vía `config/security.php` (`rate_limits.refresh`).
- `bootstrap/app.php` → en los handlers existentes de `UnauthorizedException`/`AccessDeniedHttpException` (403) y `ThrottleRequestsException` (429) se agrega la llamada a `SecurityLogger`. El cuerpo JSON de respuesta **no cambia**.
- `ProfileController` agrega `passwordPolicy()` (ver §2.4) bajo `/api/v1/profile`.
- Los Controllers siguen sin leer `ActiveBranch` salvo los existentes; nada de este spec toca datos de sede.

## 4. Contrato API

| Método | Ruta | Middleware / Permiso | Request Body | Respuesta exitosa | Errores posibles |
|---|---|---|---|---|---|
| POST | `/api/auth/login` | `throttle:login` (3 límites) | `{ "email", "password" }` | 200 sin cambios de forma | 401 `Credenciales incorrectas.`, 422, **429** |
| POST | `/api/auth/refresh` | `throttle:auth-refresh` | cookie `refresh_token` + `X-CSRF-TOKEN` | 200 sin cambios | 401, 403 (CSRF), **429** |
| POST | `/api/auth/logout` | `throttle:auth-logout` | cookie `refresh_token` | 200 sin cambios | **429** |
| POST | `/api/v1/company/logo` | `permission:configuration.update` | multipart `logo` (png/jpg/jpeg/webp ≤ 2 MB) | 200 `{ "data": { ... } }` | 422 si no es imagen permitida o es SVG |
| POST/PUT | `/api/v1/users` · `/api/v1/users/{id}` | `permission:users.create`/`users.update` | `password` con la política | sin cambios | 422 con errores de política |
| PUT | `/api/v1/profile/password` | autenticado + `throttle:api` | `current_password`, `password`, `password_confirmation` | 200 | 422 |
| GET | `/api/v1/profile/password-policy` | autenticado + `throttle:api` (solo autenticación, `user-profile.md`) | — | 200 ver abajo | 401 |

Respuesta de política:
```json
{ "data": { "min_length": 12, "mixed_case": true, "number": true, "hint": "Mínimo 12 caracteres, con mayúsculas, minúsculas y números." } }
```

Error 422 de contraseña:
```json
{ "message": "Los datos proporcionados no son válidos.", "errors": { "password": ["La contraseña debe tener al menos 12 caracteres.", "La contraseña debe incluir al menos una mayúscula y una minúscula."] } }
```

Error 429 (formato existente de `bootstrap/app.php`):
```json
{ "success": false, "message": "Demasiadas solicitudes. Por favor intente más tarde.", "retry_after": 60 }
```

Evento del canal `security` (una línea JSON):
```json
{"message":"login_failed","context":{"event":"login_failed","request_id":"7c0b…","ip":"203.0.113.9","user_id":null,"email":"ad***@empresa.com","user_agent":"Mozilla/5.0 …"},"level_name":"INFO","datetime":"2026-10-29T14:03:11-05:00"}
```

## 5. Permisos y seguridad

- No se agregan permisos Spatie nuevos. `configuration.update` (verificado en `routes/api.php:150-151`), `users.create` y `users.update` ya existen.
- `GET /api/v1/profile/password-policy` entra en la excepción de `api-authorization.md` (perfil, solo autenticación, sin datos sensibles).
- Tests 403 por acción protegida ya existentes se mantienen; se agrega el caso 403 de `POST /company/logo` si falta.
- **Matriz de amenazas cubierta:**

| Amenaza | Control |
|---|---|
| XSS reflejado/almacenado | CSP SPA + CSP API + rechazo de SVG + `nosniff` |
| Clickjacking | `X-Frame-Options: DENY` + `frame-ancestors 'none'` |
| Downgrade / MITM | HSTS |
| Forja de JWT | Secreto obligatorio ≥ 32 bytes, sin fallback |
| Fuerza bruta / spraying | 3 límites de login + límites de refresh/logout |
| Enumeración de usuarios | Hash ficticio de costo igual; mensaje único |
| CSRF / robo cross-site | CORS con lista blanca por entorno + CSRF del refresh (vigente) |
| Log injection / fuga de secretos | `sanitize()` + formato JSON + lista negra de claves |

- **Riesgo aceptado H-10:** el JWT lleva `email`, `name`, `roles`, `permissions`; el access token vive 15 min y solo en memoria; la autorización real se evalúa contra la BD con Spatie, no contra los claims. La cookie `csrf_token` legible por JS es parte del diseño de `auth-session.md` (el refresh exige además la cookie HttpOnly). Se documenta, no se cambia.

## 6. Migraciones

**No aplica** (sin tablas nuevas). Los parámetros de `auth.*` se siembran por `SystemParameterSeeder`, que es idempotente (`updateOrCreate`) y se vuelve a ejecutar en el despliegue.

## 7. Configuración e infraestructura

### 7.1 nginx de producción (`docker/nginx/default.prod.conf`)

Las directivas `add_header` **no se heredan** a un bloque `location` que declare las suyas (el actual `location ~* \.(js|css…)` declara `Cache-Control`). Por eso las cabeceras viven en un snippet incluido en `server` y en cada `location` con `add_header`:

`docker/nginx/snippets/security-headers.conf`
```nginx
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "DENY" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;
add_header Cross-Origin-Opener-Policy "same-origin" always;
add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload" always;
add_header Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'" always;
```

- `style-src 'unsafe-inline'` es necesario por los estilos en línea de Vuetify; `script-src` queda estricto (sin `unsafe-inline`/`unsafe-eval`). Si el build de Vite genera algún script en línea, se corrige en el build, no relajando la CSP.
- **Fase 1:** emitir `Content-Security-Policy-Report-Only` con el mismo valor y revisar la consola del navegador durante un recorrido completo (login, POS, finanzas, reportes, PDFs). **Fase 2:** promover a `Content-Security-Policy`.
- HSTS en este snippet solo si nginx termina TLS (Open Question). Si hay balanceador que termina TLS, se omite aquí y lo emite el balanceador o Laravel (§2.1).
- El snippet se copia a la imagen en `docker/nginx/Dockerfile.prod` (nuevo `COPY docker/nginx/snippets /etc/nginx/snippets`, junto a la línea que copia `default.prod.conf`); sin eso, el `include` falla en `nginx -t`.
- Bloque `/storage`: incluir el snippet y sobrescribir CSP: `add_header Content-Security-Policy "default-src 'none'; style-src 'unsafe-inline'; sandbox" always;` (neutraliza scripts en un SVG ya subido, H-03).
- Globales: `server_tokens off;` (en `http {}` o `conf.d`), y `fastcgi_hide_header X-Powered-By;` en la ubicación PHP.
- El server de **desarrollo** (`api.localhost`, `localhost` Vite) no recibe CSP ni HSTS (rompe el HMR de Vite).

### 7.2 PHP (`docker/php/`)

Ya existe `docker/php/php.prod.ini`, que el `Dockerfile` copia a `conf.d/99-custom.ini` en la etapa de producción (línea 72) y que ya fija `display_errors = Off`. **No se crea un archivo nuevo**: se agregan estas directivas a `php.prod.ini`:
```ini
expose_php = Off
log_errors = On
```

### 7.3 Variables de entorno

| Variable | Dónde | Defecto | Notas |
|---|---|---|---|
| `JWT_SECRET` | `.env.example` (vacío), `docker-compose.prod.yml` (`${JWT_SECRET:?…}` en los 3 servicios), entorno de test | — | ≥ 32 bytes; sin valor por defecto |
| `APP_KEY` | `.env.example` vacío | — | Rotar si se usó el histórico |
| `APP_DEBUG` | `.env.example` | `false` | Local lo activa a mano |
| `CORS_ALLOWED_ORIGINS` | `.env.example` (orígenes dev), prod obligatoria | — | CSV |
| `LOG_LEVEL` | `.env.example` | `info` | |
| `SECURITY_LOG_DAYS` | opcional | `90` | |
| `TRUSTED_PROXIES` | opcional | — | Solo si hay balanceador |

### 7.4 CORS (`config/cors.php`)

```php
'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))))),
'allowed_origins_patterns' => in_array(env('APP_ENV'), ['local', 'testing'], true)
    ? ['#^https?://([a-zA-Z0-9-]+\.)*localhost(:[0-9]+)?$#'] : [],
'allowed_headers' => ['Authorization', 'Content-Type', 'Accept', 'X-CSRF-TOKEN', 'X-Branch-Id', 'X-Request-Id', 'X-Refresh-Token', 'X-Requested-With'],
'supports_credentials' => true,
```

### 7.5 CI y auditoría de dependencias

- `Makefile`: `audit` → `docker compose run --rm back-app composer audit --no-dev` y `cd front-vue && npm audit --omit=dev --audit-level=high`.
- `.github/workflows/security-audit.yml`: `on: [pull_request, schedule(lunes 06:00 UTC)]`, jobs `composer-audit` y `npm-audit`.
- `.github/dependabot.yml`: ecosistemas `composer` (`/back-laravel`), `npm` (`/front-vue`, `/`), `github-actions`; frecuencia semanal; agrupar parches menores.
- Si el spec 006 definió CI en otra plataforma, trasladar los jobs allí (Open Question).

## 8. Frontend

- `CompanyForm.vue`: `accept="image/png,image/jpeg,image/webp"`; texto de ayuda: "Formatos aceptados: PNG, JPG, JPEG o WEBP. Tamaño máximo: 2 MB."
- Formularios de contraseña (usuarios y `SecurityTab.vue`): obtener la política con `GET /api/v1/profile/password-policy` (composable `usePasswordPolicy()`, cacheado en memoria) y mostrarla como `hint` del `VTextField` (`type=password`, `label="Nueva contraseña *"`, `placeholder="Ej.: MiClave#2026Segura"`), más indicador de fuerza opcional. Los 422 se muestran con `useApiFormErrors` sin toast (`frontend-ui.md`).
- Manejo del 429: `utils/api.js` ya intercepta errores; mostrar `useConfirm().alert({ message })` con el `Retry-After` en login. Sin diálogos propios.
- `front-vue/src/tests/unit/uiGuardrails.spec.js` y `routeMeta.spec.js` no se modifican.
- MSW: agregar handler de `GET /api/v1/profile/password-policy` en los mocks para el modo sin backend.
- Estados: carga (skeleton del hint), vacío (política por defecto local de 12 caracteres si el endpoint falla), error (no bloquea el formulario; el backend es la fuente de verdad).

## 9. Testing

Backend (siempre con `make test-run ARGS="--filter=X"` o `make test`, **nunca** `php artisan test` en el host — `testing.md`). Carpeta `back-laravel/tests/Feature/Security/` y `tests/Unit/Security/`:

| Test | Verifica |
|---|---|
| `SecurityHeadersTest` | Cabeceras de US-1 en `/api/*`; HSTS solo con HTTPS simulado y fuera de `local`/`testing`; ausencia de `X-XSS-Protection: 1` |
| `JwtSecretTest` | `new JwtService()` lanza con secreto vacío o < 32 bytes; no existe el literal por defecto (grep del repositorio en test de arquitectura) |
| `CompanyLogoUploadTest` | SVG con `<script>` → 422; PNG/JPG/WEBP válidos → 200; 403 sin `configuration.update` |
| `AuthRateLimitTest` | 429 en refresh (31.ª), logout (31.ª), login distribuido (16 intentos con IPs distintas, mismo email) |
| `LoginTimingTest` | `Hash::check` se invoca también con usuario inexistente (spy sobre el hasher) |
| `PasswordPolicyTest` | 11/12 caracteres, sin mayúscula, sin número; `check_pwned` apagado no hace petición externa; con chequeo activo y fallo de red no bloquea (`Http::fake`) |
| `CorsConfigTest` | Preflight desde origen no listado sin `Access-Control-Allow-Origin`; listado con él; sin `*` con credenciales |
| `SecurityLoggerTest` | Línea `login_failed` presente; sin contraseña/token; `\r\n` saneado; email enmascarado (`Log::shouldReceive` / canal `single` de test) |
| `EnsureRequestIdTest` | Válido se conserva; inválido (espacios, `\n`, 65 caracteres) se reemplaza por UUID |
| `CheckCompanyLogoCommandTest` | Detecta logo SVG sin borrarlo |
| `RouteAuthorizationGuardTest` | Recorre `Route::getRoutes()` y falla si una ruta `api/v1/*` no tiene `permission:*` y no está en la lista blanca (todos los `/options`, `/company/summary`, `/profile*` y las cinco rutas nombradas de requirements US-11); el mensaje indica método y URI (US-11) |
| Regresión | Suites existentes `AuthTest`, `RateLimitingTest`, `ExceptionHandlingTest`, `ProfileTest` siguen en verde (ajustar solo lo que dependa de `min:8` y del mensaje de política) |

Frontend: `cd front-vue && npm run lint`; test unitario de `usePasswordPolicy`.
Infra (manual, documentado en `todo.md`): `curl -sI https://<host>/` y `curl -sI https://<host>/api/up` y revisión de consola del navegador con CSP en `Report-Only`.
Calidad: `cd back-laravel && vendor/bin/pint --test`.
Tras modificar código: `graphify update .`.

## 10. Decisiones Rechazadas

- **Mantener fallback de `JWT_SECRET` a `APP_KEY`:** acopla dos secretos y reduce el aislamiento; además la clave de ejemplo ya está en el historial de git. Se exige `JWT_SECRET` propio.
- **Sanitizar SVG con una librería en lugar de prohibirlo:** el logo se usa en PDFs (`PdfRenderer`) y cabeceras; PNG/WEBP cubren el caso. Sanitizar SVG añade dependencia y riesgo de bypass.
- **CSP solo en Laravel:** Laravel no sirve el HTML de la SPA en producción; la CSP efectiva debe estar en nginx.
- **CSP con nonce por petición:** la SPA es estática (Vite), sin render de servidor; `script-src 'self'` basta.
- **Bloqueo de cuenta permanente tras N intentos:** habilita denegación de servicio contra cuentas ajenas; se prefiere límite por ventana de tiempo.
- **Unificar 401 y 403 del refresh:** contradice `auth-session.md` (CSRF no coincide = 403 sin borrar cookies) y rompe el comportamiento multi-pestaña.
- **Endpoint público para la política de contraseñas:** innecesario; solo se necesita autenticado (formularios de perfil/usuarios). El login no muestra política.
- **`Password::uncompromised()` siempre activo:** envía prefijos de hash a un servicio externo (acción hacia afuera) y falla en entornos sin salida a Internet; queda como parámetro apagado por defecto.
- **Mover `X-XSS-Protection` a `0` en lugar de eliminarlo:** equivalente; se elimina para reducir ruido.
- **Cambiar JWT a RS256 en este spec:** mejora válida pero invasiva (gestión de claves, rotación); fuera de alcance (ver Out of Scope).

## 11. Decisiones tomadas

Registro único de lo que **sí** se decidió. Cada fila enlaza a la sección con el detalle; las alternativas descartadas están en §10.

| # | Decisión | Motivo | Hallazgo | Detalle |
|---|---|---|---|---|
| D-1 | `JWT_SECRET` obligatorio, ≥ 32 bytes, sin fallback a `APP_KEY` ni a literal; la app falla al arrancar en producción | Un secreto conocido permite forjar tokens con cualquier rol | H-02 | §2.2, §7.3 |
| D-2 | La CSP de la SPA se emite desde nginx; la de la API desde Laravel (`default-src 'none'`) | Laravel no sirve el HTML de la SPA en producción | H-01 | §2.1, §7.1 |
| D-3 | CSP en `Report-Only` primero, enforcing después de un recorrido completo sin violaciones | Evita romper la SPA sin haberla probado | H-01 | §7.1, `todo.md` T-10 y T-15 |
| D-4 | `script-src 'self'` sin `unsafe-inline` ni `unsafe-eval`; `style-src` sí admite `unsafe-inline` | Vuetify usa estilos en línea; los scripts no deben relajarse | H-01 | §7.1 |
| D-5 | Las cabeceras de nginx viven en un snippet incluido en `server` y en cada `location` con `add_header` | nginx no hereda `add_header` hacia un `location` que declara el suyo | H-01 | §7.1 |
| D-6 | HSTS solo bajo HTTPS y fuera de `local`/`testing`; el emisor (nginx o Laravel) depende de dónde termina TLS | Un HSTS sobre HTTP local rompe el desarrollo | H-01 | §2.1, requirements §7 |
| D-7 | Se prohíbe el SVG como logo (no se sanea) y `/storage` se sirve con CSP `sandbox` | PNG/WEBP cubren el caso; sanear SVG añade dependencia y riesgo de bypass | H-03 | §2.7, §7.1 |
| D-8 | `company:check-logo` solo informa, no borra | Borrar un archivo de producción sin confirmación es irreversible | H-03 | §2.7 |
| D-9 | Refresh y logout: 30/min por IP, ajustable en `config/security.php` | Cubre varias pestañas tras un NAT sin romper el refresh silencioso | H-04 | §3 |
| D-10 | Se conserva 401 (token inválido) / 403 (CSRF no coincide) del refresh | Contrato de `auth-session.md`; unificarlo rompe el multi-pestaña | H-04 | §5, §10 |
| D-11 | Login con tres límites (`email\|ip`, `email`, `ip`) y `Hash::check` siempre, con hash ficticio memoizado | Evita fuerza bruta distribuida y enumeración por tiempo | H-05 | §2.3 |
| D-12 | Sin bloqueo permanente de cuentas; solo ventanas de tiempo | Un bloqueo permanente permite denegar el servicio a cuentas ajenas | H-05 | §10 |
| D-13 | El estado "usuario inactivo" se informa solo tras contraseña válida (ya es así) | El mensaje solo llega a quien conoce la contraseña | H-05 | §2.3 |
| D-14 | Política de contraseñas centralizada en `PasswordPolicy`, umbrales en `system_parameters` (grupo `auth`) | Regla `business-parameters.md`: nada configurable como constante | H-06 | §2.4 |
| D-15 | `uncompromised()` apagado por defecto y fail-open si el servicio externo no responde | Envía datos hacia afuera y falla sin Internet | H-06 | §2.4, §10 |
| D-16 | La nueva política no invalida contraseñas ni sesiones existentes | Evita bloquear a todos los usuarios al desplegar | H-06 | requirements §4 |
| D-17 | La política se expone en `GET /api/v1/profile/password-policy` (solo autenticación) | El front muestra la ayuda sin duplicar reglas; entra en la excepción de perfil | H-06 | §2.4, §4 |
| D-18 | CORS: orígenes desde `CORS_ALLOWED_ORIGINS`; patrones `localhost` solo en `local`/`testing`; cabeceras enumeradas | Producción nunca debe aceptar orígenes de desarrollo | H-07 | §7.4 |
| D-19 | Canal `security` separado, JSON, 90 días, sin contraseñas ni tokens, saneado de `\r\n` y email enmascarado | Trazabilidad sin fugas ni log injection | H-08 | §2.5 |
| D-20 | `SecurityLogger` lo llama el controller o el handler; los servicios de dominio no leen `Request` | Compatible con `multi-branch.md` (jobs y scheduler) | H-08 | §2.5 |
| D-21 | El canal `security` complementa el activity log (spec 007), no lo reemplaza | El activity log audita datos de dominio; este audita autenticación y abuso | H-08 | requirements §4 |
| D-22 | `X-Request-Id` solo se acepta con `^[A-Za-z0-9-]{8,64}$`; si no, UUID | El cliente no debe controlar el identificador de trazabilidad | H-09 | §2.6 |
| D-23 | H-10 (claims en el JWT, cookie CSRF legible) queda como **riesgo aceptado y documentado** | Access token de 15 min solo en memoria; la autorización real se evalúa en BD | H-10 | §5 |
| D-24 | `X-Frame-Options: DENY` y se elimina `X-XSS-Protection` | `DENY` es lo recomendado; la cabecera XSS está obsoleta | H-11 | §2.1 |
| D-25 | `server_tokens off`, `fastcgi_hide_header X-Powered-By` y `expose_php = Off` | Reduce la huella de versiones | H-12 | §7.1, §7.2 |
| D-26 | `make audit` + workflow semanal y por PR + Dependabot; las vulnerabilidades de terceros que aparezcan se abren como tareas aparte | Este spec instala el control, no corrige dependencias | H-13 | §7.5 |
| D-27 | `.env.example` con `APP_KEY` y `JWT_SECRET` vacíos y `APP_DEBUG=false`; un target nuevo `make secrets` imprime valores aleatorios | Una plantilla copiada a producción no debe heredar secretos ni depuración | H-14 | §2.2, §7.3 |
| D-28 | Los fragmentos SQL interpolados son `private const` con comentario que prohíbe entrada de usuario | Hoy no es explotable; la constante evita que lo sea mañana | H-15 | §2.8 |
| D-29 | Test automático que falla si una ruta `api/v1/*` queda sin `permission:*` (lista blanca fija) | Convierte `api-authorization.md` en control verificable (deny by default) | — (preventivo) | §9, requirements US-11 |
| D-31 | Los compose de dev/test usan un secreto `dev-only-…` explícito; `JwtService` lo rechaza en producción | Los tests y el desarrollo necesitan un valor, pero nunca debe llegar a producción | H-02 | §2.2 |
| D-32 | Las directivas de PHP se agregan a `php.prod.ini` existente | Ya está copiado por el `Dockerfile`; evita un segundo archivo | H-12 | §7.2 |
| D-30 | Sin tablas ni migraciones nuevas | Los parámetros usan `system_parameters`; los logs van a archivo | — | §1, §6 |

**Decisiones diferidas** (siguen abiertas, con supuesto provisional en `requirements.md` §7): terminación de TLS, activar `check_pwned`, plataforma de CI, existencia de logos SVG en producción, rotación de la `APP_KEY` histórica, retención del canal `security` y las cinco rutas de lectura sin permiso (D-29).
