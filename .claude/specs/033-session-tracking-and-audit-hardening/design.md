# 033 · session-tracking-and-audit-hardening — Design
> **Spec:** 033 · **Alcance:** fullstack · **Módulo:** Auth/Profile + Audit (transversal) · **Prioridad:** alta · **Depende de specs:** 005, 007, 009
> **Status:** In Progress
> **Última actualización:** 2026-10-23
> **Autores:** Luis Acuña Guevara
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md) — define el qué/por qué; este documento define el cómo.
> **Reglas:** [.agents/rules/](../../../.agents/rules/) · **Makefile:** [Makefile](../../../Makefile)
>
---
## 0. Ubicación y alcance

Backend, módulo por módulo:
- **`app/Modules/Auth/`** — nuevo modelo `Session`, `RefreshToken` modificado, `JwtService` modificado, `JwtAuthenticate` modificado.
- **`app/Modules/Profile/`** — `ProfileService` modificado (sesiones ahora sobre `Session`, no sobre `RefreshToken`), `ProfileController`/`SessionResource` nuevo.
- **`app/Modules/Audit/`** — `Activity` modificado (deja de depender de `ActiveBranch` directamente), nuevo `AuditContext` (en `app/Support/Audit/`), nuevo `ActivityRecorder` helper para llamadas manuales, `AuditLogResource`/`ListAuditLogsRequest` ampliados con `session_id`.
- **`app/Support/Auth/`** — nuevo `CurrentSession` (mismo patrón que `App\Support\Branch\ActiveBranch`).
- **Providers de módulo** (`PurchasesServiceProvider`, `FinanceServiceProvider`, `InventoryServiceProvider`, `SalesServiceProvider`) y nuevos registros de `morphMap` en los módulos que hoy caen a FQCN (`Users`, `Sales\DocumentSeries`, `Finance\BankAccount`, `Finance\ExchangeRate`, `Purchases\PurchaseRequest`, `Purchases\ProviderPrice`).
- **5 servicios con llamadas manuales a `activity()`**: `InstallmentScheduleService`, `StockQueryService`, `UserBranchService`, `PurchaseOrderWorkflowService`, `RoleService`.
- **1 job de referencia**: `SendElectronicDocumentJob`.

Frontend:
- **`front-vue/src/modules/profile/`** — `stores/profileStore.js`, `components/SessionsTab.vue`.

No aplica: no hay cambios en `config/session.php` más allá de documentarlo (ver §7); no se reabre el spec 007 ni se reescribe `AuditLogController`/`AuditLogService`/`AuditLogResource` (solo se extienden).

---
## 1. Backend — Modelo y Persistencia

### 1.1 `Session` (nuevo)
`back-laravel/app/Modules/Auth/Models/Session.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Models;

use App\Modules\Users\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Sesión lógica de un dispositivo/navegador. Sobrevive a la rotación de
 * refresh tokens: el `id` y el `created_at` de esta fila son estables desde
 * el login hasta que la sesión se revoca; `refresh_tokens.session_id` enlaza
 * cada fila rotada a la misma sesión.
 *
 * @property string $id
 * @property string $user_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string $device_type
 * @property string|null $browser
 * @property string|null $platform
 * @property Carbon $last_used_at
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property-read User $user
 * @property-read HasMany<RefreshToken, $this> $refreshTokens
 */
class Session extends Model
{
    use HasUuids;

    protected $table = 'sessions';

    protected $keyType = 'string';

    public $incrementing = false;

    public const DEVICE_DESKTOP = 'desktop';

    public const DEVICE_MOBILE = 'mobile';

    public const DEVICE_TABLET = 'tablet';

    public const DEVICE_UNKNOWN = 'unknown';

    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'platform',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<RefreshToken, $this>
     */
    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(?CarbonInterface $now = null): bool
    {
        return $this->expires_at->lessThanOrEqualTo($now ?? now());
    }

    public function isActive(?CarbonInterface $now = null): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired($now);
    }
}
```

**Por qué `device_type`/`browser`/`platform` viven en la fila y no se recalculan en cada lectura:** se resuelven una sola vez, en `JwtService::createTokenPair()`/`rotateRefreshToken()` cuando se conoce el `user_agent` crudo (ver §1.3), con un parser propio (`UserAgentParser`, §1.4). Esto saca ese cálculo del frontend (`SessionsTab.vue` hoy lo hace con regex en cada render — US-3) y lo deja estable aunque cambien las reglas de parseo futuras (una sesión vieja conserva el valor con el que se creó, no se reinterpreta retroactivamente).

### 1.2 `RefreshToken` (modificado)
`back-laravel/app/Modules/Auth/Models/RefreshToken.php` — se elimina `ip_address`/`user_agent`, se agrega `session_id` y la relación `session()`:

```php
<?php

namespace App\Modules\Auth\Models;

use App\Modules\Users\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $session_id
 * @property string $token_hash
 * @property string $csrf_token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $grace_period_expires_at
 * @property-read User $user
 * @property-read Session $session
 */
class RefreshToken extends Model
{
    use HasUuids;

    protected $table = 'refresh_tokens';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'session_id',
        'token_hash',
        'csrf_token_hash',
        'expires_at',
        'revoked_at',
        'grace_period_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'grace_period_expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Session, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function isExpired(?CarbonInterface $now = null): bool
    {
        return $this->expires_at->lessThanOrEqualTo($now ?? now());
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isWithinGracePeriod(?CarbonInterface $now = null): bool
    {
        if (! $this->isRevoked() || $this->grace_period_expires_at === null) {
            return false;
        }

        return $this->grace_period_expires_at->greaterThan($now ?? now());
    }

    public function verifyCsrfToken(string $csrfToken): bool
    {
        return hash_equals($this->csrf_token_hash, hash('sha256', $csrfToken));
    }
}
```

Nota de diseño: `RefreshToken::isRevoked()` sigue mirando **solo su propia** columna; la regla de negocio "una sesión revocada no puede rotar" (US-2) se aplica en `JwtService::rotateRefreshToken()` consultando explícitamente `$storedToken->session`, no metiendo esa responsabilidad dentro del modelo (el modelo no decide reglas de flujo, igual que el resto del proyecto separa Model/Service).

### 1.3 `UserAgentParser` (nuevo, sin dependencia externa)
`back-laravel/app/Support/Auth/UserAgentParser.php`

No se agrega ninguna librería de terceros (no hay ninguna en `composer.json` hoy — ver Open Question de `requirements.md`, resuelta aquí): un parser de 15 líneas basado en los mismos patrones que hoy vive duplicado en `SessionsTab.vue` es suficiente para el objetivo (icono + nombre de navegador en una lista de "mis dispositivos"), y evita arrastrar una dependencia con su propio ciclo de mantenimiento para un caso de uso no crítico (no se usa para control de acceso ni fingerprinting de seguridad).

```php
<?php

declare(strict_types=1);

namespace App\Support\Auth;

final class UserAgentParser
{
    /**
     * @return array{device_type: string, browser: string|null, platform: string|null}
     */
    public static function parse(?string $userAgent): array
    {
        $ua = strtolower((string) $userAgent);

        return [
            'device_type' => self::deviceType($ua),
            'browser' => self::browser($ua),
            'platform' => self::platform($ua),
        ];
    }

    private static function deviceType(string $ua): string
    {
        if ($ua === '') {
            return \App\Modules\Auth\Models\Session::DEVICE_UNKNOWN;
        }

        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet')) {
            return \App\Modules\Auth\Models\Session::DEVICE_TABLET;
        }

        if (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) {
            return \App\Modules\Auth\Models\Session::DEVICE_MOBILE;
        }

        return \App\Modules\Auth\Models\Session::DEVICE_DESKTOP;
    }

    private static function browser(string $ua): ?string
    {
        if ($ua === '') {
            return null;
        }

        return match (true) {
            str_contains($ua, 'edg/') => 'Microsoft Edge',
            str_contains($ua, 'firefox') => 'Mozilla Firefox',
            str_contains($ua, 'chrome') => 'Google Chrome',
            str_contains($ua, 'safari') => 'Apple Safari',
            default => 'Navegador web',
        };
    }

    private static function platform(string $ua): ?string
    {
        if ($ua === '') {
            return null;
        }

        return match (true) {
            str_contains($ua, 'windows') => 'Windows',
            str_contains($ua, 'mac os') => 'macOS',
            str_contains($ua, 'android') => 'Android',
            str_contains($ua, 'iphone'), str_contains($ua, 'ipad') => 'iOS',
            str_contains($ua, 'linux') => 'Linux',
            default => null,
        };
    }
}
```

### 1.4 `CurrentSession` (nuevo) — mismo patrón que `ActiveBranch`
`back-laravel/app/Support/Auth/CurrentSession.php`

```php
<?php

declare(strict_types=1);

namespace App\Support\Auth;

class CurrentSession
{
    private ?string $sessionId = null;

    public function set(string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    public function has(): bool
    {
        return $this->sessionId !== null;
    }

    public function id(): string
    {
        if ($this->sessionId === null) {
            throw new \LogicException('CurrentSession no ha sido resuelta para esta petición.');
        }

        return $this->sessionId;
    }
}
```

Se registra como *singleton* por request en `AppServiceProvider` (o un provider de `Auth`), igual que `ActiveBranch`. **No se resuelve consultando la tabla `sessions`**: el `id` viaja en el claim `sid` del access token (ver §2.1), así que `JwtAuthenticate` lo asigna sin ninguna consulta extra a la base de datos.

### 1.5 `AuditContext` (nuevo) — desacopla `Activity` de `Branch`

Hoy `App\Modules\Audit\Models\Activity::booted()` llama directamente `app(ActiveBranch::class)` — el módulo `Audit` queda acoplado al módulo `Branch`/`Maintenance`. Se introduce un punto de indirección neutral:

`back-laravel/app/Support/Audit/AuditContext.php`

```php
<?php

declare(strict_types=1);

namespace App\Support\Audit;

class AuditContext
{
    private ?string $branchId = null;

    private ?string $sessionId = null;

    public function setBranchId(?string $branchId): void
    {
        $this->branchId = $branchId;
    }

    public function setSessionId(?string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    public function branchId(): ?string
    {
        return $this->branchId;
    }

    public function sessionId(): ?string
    {
        return $this->sessionId;
    }
}
```

`AuditContext` es un *singleton* por request, igual que `ActiveBranch`/`CurrentSession`, pero **neutral**: no importa nada de `Branch` ni de `Auth`. Quien lo rellena es la capa de presentación (`JwtAuthenticate`, el middleware `branch`) o, explícitamente, un job antes de tocar el modelo (§3).

**Corrección respecto a la primera versión de esta sección:** la primera implementación guardaba `branch_id`/`session_id` como claves dentro de `properties` (JSON), igual que ya hacía el código previo a este spec. Tras evaluar con el dueño del producto el costo de auditar/filtrar a esa escala (`properties` es `json`, no `jsonb`: sin índice, cada filtro escanea fila por fila), se promovieron a **columnas propias e indexadas** de `activity_log` (ver §6.5) — mismo nivel que `causer_id`/`subject_id`. `Activity::booted()` escribe directamente los atributos del modelo, no `properties`:

```php
protected static function booted(): void
{
    static::saving(function (Activity $activity): void {
        $context = app()->bound(AuditContext::class) ? app(AuditContext::class) : null;

        // branch_id: preferir el de la propia entidad auditada (no requiere contexto de
        // request y funciona igual en requests, jobs y comandos) y usar AuditContext
        // solo como respaldo para subjects sin columna branch_id propia.
        if ($activity->branch_id === null) {
            $activity->branch_id = $activity->subject?->getAttribute('branch_id') ?? $context?->branchId();
        }

        if ($activity->session_id === null) {
            $activity->session_id = $context?->sessionId();
        }
    });
}
```

**Esto resuelve, sin ningún cambio en jobs, el caso real observado** (`SendElectronicDocumentJob` deja `branch_id` nulo): como `Sale`/`CreditNote` ya tienen columna `branch_id`, la actividad generada al actualizar `einvoice_status` en cola la toma directamente del propio `subject`, sin depender de que `ActiveBranch`/`AuditContext` esté *bound* en el worker. `session_id` y `causer` sí requieren que el job los reciba explícitamente (no todo cambio en cola es indefectiblemente "continuación de una sesión humana"), ver §3.

---
## 2. Backend — Lógica de Negocio y Servicios

### 2.1 `JwtService` (modificado)

Cambios sobre `back-laravel/app/Modules/Auth/Services/JwtService.php`:

- `issueAccessToken(User $user, string $sessionId)` agrega el claim `'sid' => $sessionId` (no cambia nada más del contrato del token; `exp`/`iat`/`tv`/`roles`/`permissions` quedan igual).
- `createTokenPair()` ahora **crea la `Session` si no se le pasa una existente**, y siempre crea una `RefreshToken` con `session_id`:

```php
/**
 * @return array<string, mixed>
 */
public function createTokenPair(User $user, ?string $ipAddress = null, ?string $userAgent = null, ?Session $session = null): array
{
    $ip = $ipAddress ?? (function_exists('request') && request() ? request()->ip() : null);
    $ua = $userAgent ?? (function_exists('request') && request() ? request()->userAgent() : null);
    $uaInfo = UserAgentParser::parse($ua);

    if ($session === null) {
        $session = Session::create([
            'user_id' => $user->id,
            'ip_address' => $ip,
            'user_agent' => $ua ? substr($ua, 0, 500) : null,
            'device_type' => $uaInfo['device_type'],
            'browser' => $uaInfo['browser'],
            'platform' => $uaInfo['platform'],
            'last_used_at' => now(),
            'expires_at' => now()->addSeconds($this->refreshExpiresIn),
        ]);
    } else {
        $session->update([
            'ip_address' => $ip,
            'user_agent' => $ua ? substr($ua, 0, 500) : null,
            'device_type' => $uaInfo['device_type'],
            'browser' => $uaInfo['browser'],
            'platform' => $uaInfo['platform'],
            'last_used_at' => now(),
            'expires_at' => now()->addSeconds($this->refreshExpiresIn),
        ]);
    }

    $accessToken = $this->issueAccessToken($user, $session->id);
    $refreshTokenString = $this->generateRefreshToken();
    $csrfTokenString = $this->generateCsrfToken();

    RefreshToken::create([
        'user_id' => $user->id,
        'session_id' => $session->id,
        'token_hash' => hash('sha256', $refreshTokenString),
        'csrf_token_hash' => hash('sha256', $csrfTokenString),
        'expires_at' => now()->addSeconds($this->refreshExpiresIn),
    ]);

    return [
        'access_token' => $accessToken,
        'refresh_token' => $refreshTokenString,
        'csrf_token' => $csrfTokenString,
        'token_type' => 'Bearer',
        'expires_in' => $this->accessExpiresIn,
        'session_id' => $session->id,
    ];
}
```

- `rotateRefreshToken()` pasa a bloquear la `Session` (`lockForUpdate()`, orden determinista por ser una sola fila) antes de decidir si rota, y **reutiliza la sesión** en vez de crear una nueva:

```php
public function rotateRefreshToken(string $refreshTokenString, ?string $csrfToken): array
{
    $tokenHash = hash('sha256', $refreshTokenString);

    return DB::transaction(function () use ($tokenHash, $csrfToken) {
        /** @var RefreshToken|null $storedToken */
        $storedToken = RefreshToken::where('token_hash', $tokenHash)->first();

        if (! $storedToken) {
            throw new \RuntimeException('Token de actualización inválido o no encontrado.', 401);
        }

        if (empty($csrfToken) || ! $storedToken->verifyCsrfToken($csrfToken)) {
            throw new \RuntimeException('Token CSRF inválido o no coincide.', 403);
        }

        if ($storedToken->isExpired()) {
            throw new \RuntimeException('Token de actualización expirado.', 401);
        }

        if ($storedToken->isRevoked()) {
            if ($storedToken->isWithinGracePeriod()) {
                $cached = Cache::get('rotated_pair_'.$tokenHash);
                if (is_array($cached)) {
                    return $cached;
                }
            }
            throw new \RuntimeException('Token de actualización revocado.', 401);
        }

        /** @var Session $session */
        $session = Session::where('id', $storedToken->session_id)->lockForUpdate()->firstOrFail();

        if (! $session->isActive()) {
            throw new \RuntimeException('Sesión revocada o expirada.', 401);
        }

        $storedToken->update([
            'revoked_at' => now(),
            'grace_period_expires_at' => now()->addSeconds($this->gracePeriodSeconds),
        ]);

        $user = $storedToken->user;
        if (! $user instanceof User || $user->status !== User::STATUS_ACTIVE) {
            throw new \RuntimeException('Usuario inactivo.', 401);
        }

        $ip = function_exists('request') && request() ? request()->ip() : null;
        $ua = function_exists('request') && request() ? request()->userAgent() : null;

        $newPair = $this->createTokenPair(
            user: $user,
            ipAddress: $ip ?? $session->ip_address,
            userAgent: $ua ?? $session->user_agent,
            session: $session,
        );

        Cache::put('rotated_pair_'.$tokenHash, $newPair, now()->addSeconds($this->gracePeriodSeconds));

        return $newPair;
    });
}
```

- Nuevo método `revokeSession(Session $session): void` — lock + revoca sesión y su refresh token activo en una transacción (usado por `logout`, `revokeOtherSessions`, `updatePassword`):

```php
public function revokeSession(string $sessionId): void
{
    DB::transaction(function () use ($sessionId) {
        /** @var Session $session */
        $session = Session::where('id', $sessionId)->lockForUpdate()->firstOrFail();

        if ($session->isRevoked()) {
            return;
        }

        $session->update(['revoked_at' => now()]);

        RefreshToken::where('session_id', $session->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    });
}

/**
 * @return int Número de sesiones revocadas.
 */
public function revokeOtherSessions(string $userId, ?string $currentSessionId): int
{
    return DB::transaction(function () use ($userId, $currentSessionId) {
        $sessions = Session::where('user_id', $userId)
            ->whereNull('revoked_at')
            ->when($currentSessionId !== null, fn ($q) => $q->where('id', '!=', $currentSessionId))
            ->orderBy('id') // orden determinista: evita deadlocks si dos requests revocan en paralelo
            ->lockForUpdate()
            ->get();

        foreach ($sessions as $session) {
            $session->update(['revoked_at' => now()]);
            RefreshToken::where('session_id', $session->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        }

        return $sessions->count();
    });
}

public function revokeRefreshToken(?string $refreshTokenString): void
{
    if (empty($refreshTokenString)) {
        return;
    }

    $tokenHash = hash('sha256', $refreshTokenString);
    $storedToken = RefreshToken::where('token_hash', $tokenHash)->first();

    if ($storedToken === null) {
        return;
    }

    $this->revokeSession($storedToken->session_id);
}
```

El resto de `JwtService` (`issueAccessToken`, `validateAccessToken`, `generateRefreshToken`, `generateCsrfToken`, cookies) no cambia de forma relevante más allá del claim `sid` ya mencionado.

### 2.2 `ProfileService` (modificado)

`listSessions()`/`revokeOtherSessions()` pasan a operar sobre `Session`:

```php
/**
 * @return Collection<int, array{id: string, ip_address: string|null, device_type: string, browser: string|null, platform: string|null, created_at: string|null, last_used_at: string|null, is_current: bool}>
 */
public function listSessions(User $user, ?string $currentSessionId = null): Collection
{
    /** @var Collection<int, Session> $sessions */
    $sessions = Session::where('user_id', $user->id)
        ->whereNull('revoked_at')
        ->where('expires_at', '>', now())
        ->orderByDesc('last_used_at')
        ->get();

    return $sessions->map(fn (Session $session) => [
        'id' => $session->id,
        'ip_address' => $session->ip_address,
        'device_type' => $session->device_type,
        'browser' => $session->browser,
        'platform' => $session->platform,
        'created_at' => $session->created_at?->toIso8601String(),
        'last_used_at' => $session->last_used_at->toIso8601String(),
        'is_current' => $currentSessionId !== null && $session->id === $currentSessionId,
    ]);
}

public function revokeOtherSessions(User $user, ?string $currentSessionId = null): int
{
    return $this->jwtService->revokeOtherSessions($user->id, $currentSessionId);
}
```

`updatePassword()` cambia la fuente de "revocar todo lo anterior" de `RefreshToken` a `Session`:

```php
public function updatePassword(User $user, string $newPassword): array
{
    return DB::transaction(function () use ($user, $newPassword) {
        $user->update(['password' => $newPassword]);
        $user->increment('token_version');

        Session::where('user_id', $user->id)->whereNull('revoked_at')->get()
            ->each(fn (Session $session) => $this->jwtService->revokeSession($session->id));

        $freshUser = $user->fresh() ?? $user;

        return $this->jwtService->createTokenPair($freshUser);
    });
}
```

`ProfileController::listSessions()`/`revokeOtherSessions()` ya no extraen el *refresh token* de la cookie para identificar "la sesión actual": ahora el *access token* validado por `JwtAuthenticate` ya trae el claim `sid`, expuesto como `$request->attributes->get('session_id')` (ver §2.3). Esto además corrige una inconsistencia menor: antes, "sesión actual" se resolvía comparando el hash del refresh token de la cookie, que podía no coincidir si ya había rotado en otra pestaña (mitigado hoy por el periodo de gracia, pero sigue siendo un acoplamiento innecesario); comparar por `session_id` del access token vigente es más directo.

### 2.3 `JwtAuthenticate` (modificado)

Tras resolver `$user` con éxito, antes de `$next($request)`:

```php
$sessionId = $claims['sid'] ?? null;

if (is_string($sessionId) && $sessionId !== '') {
    $request->attributes->set('session_id', $sessionId);
    app(CurrentSession::class)->set($sessionId);
    app(AuditContext::class)->setSessionId($sessionId);
}
```

Si `$rotatedTokens` viene de una renovación silenciosa (access token expirado a mitad de request), se usa `$rotatedTokens['session_id']` en su lugar (la sesión es la misma, pero conviene no depender del claim viejo).

El middleware `branch` (fuera de este spec, ya existente) sigue poblando `ActiveBranch`; se agrega una línea para que también haga `app(AuditContext::class)->setBranchId($branch->id)`, sustituyendo la lectura directa que hoy hace `Activity` desde `ActiveBranch`.

---
## 3. Backend — Jobs y causer/sesión explícitos (US-4)

### 3.1 Regla de diseño

No todo cambio de estado producido por un job es, honestamente, "causado por" la sesión que disparó el job. Se distinguen dos categorías y se tratan distinto:

1. **Continuación determinista de la acción del usuario** (el job solo ejecuta en segundo plano lo que el request ya decidió): el job recibe `causerId`/`sessionId` explícitos en su constructor, serializados al encolar, y los usa para `causedBy()`/`AuditContext` al tocar el modelo.
2. **Reacción a un sistema externo** (p. ej. la respuesta de SUNAT/el driver de facturación electrónica decide `accepted`/`rejected`/`error`, no el usuario): el causer real es "el sistema", no la persona que emitió el documento. Forzar un `causer_id` humano aquí sería engañoso. Se deja `causer_type`/`causer_id` en `NULL` **pero de forma explícita y distinguible**, agregando `'system' => true` a las `properties` del log (convención nueva, ver §9 CLAUDE.md.delta) en vez de un `NULL` silencioso indistinguible de "se nos olvidó".

`SendElectronicDocumentJob` cae en la categoría 2 (reacciona al resultado del driver de e-invoicing), pero su `branch_id` **ya queda resuelto automáticamente** por el cambio de §1.5 (se toma de `$document->branch_id`, no requiere tocar el job). Solo se agrega la marca `system`:

```php
// dentro de handle(), al loguear manualmente el resultado (adicional al log automático de LogsActivity):
activity()
    ->performedOn($document)
    ->event('einvoice_status_updated')
    ->withProperties([
        'attributes' => ['einvoice_status' => $result->status],
        'old' => ['einvoice_status' => $previousStatus],
        'system' => true,
    ])
    ->log("Facturación electrónica: {$result->status->value}");
```

(En la práctica el log automático de `LogsActivity` ya captura el diff de `einvoice_status`; este bloque documenta el patrón de marca `system` para **cualquier** job nuevo que actualice un modelo auditable sin causer humano — no se duplica el log en `SendElectronicDocumentJob` si el automático es suficiente. Ver tarea T-11 en `todo.md`.)

### 3.2 Ejemplo de job de categoría 1 (referencia, no existe aún en el código)

Si un job futuro continúa una acción de usuario (p. ej. recalcular algo que el usuario pidió), su constructor declara `public readonly ?string $causerId` y `public readonly ?string $sessionId`, y en `handle()`:

```php
if ($this->causerId !== null) {
    app(AuditContext::class)->setSessionId($this->sessionId);
    activity()->causedBy(User::find($this->causerId))->performedOn($model)->...->log(...);
}
```

Esto se documenta como convención para que el equipo no vuelva a repetir el gap encontrado en `SendElectronicDocumentJob` (job sin ningún rastro de quién/qué sesión originó la emisión del documento).

---
## 4. Contrato API

| Método | Ruta | Middleware / Permiso | Request Body | Respuesta exitosa | Errores posibles |
|---|---|---|---|---|---|
| POST | `/api/v1/auth/login` | `throttle:login` | `{email, password}` | `200` igual que hoy + cookies `refresh_token`/`csrf_token` (sin cambio de forma; internamente crea `Session`) | `401` credenciales/usuario inactivo |
| POST | `/api/v1/auth/refresh` | — | cookie `refresh_token` + header/cookie `csrf_token` | `200` igual que hoy | `401` sesión/token revocado o expirado, `403` CSRF no coincide |
| POST | `/api/v1/auth/logout` | — | cookie `refresh_token` | `200 {"message": "Sesión cerrada correctamente."}` | — |
| GET | `/api/v1/profile/sessions` | `JwtAuthenticate` | — | `200 {"success": true, "data": [{"id","ip_address","device_type","browser","platform","created_at","last_used_at","is_current"}], "message": "..."}` | `401` |
| DELETE | `/api/v1/profile/sessions/others` | `JwtAuthenticate` | — | `200 {"success": true, "data": {"revoked_sessions_count": N}}` | `401` |
| GET | `/api/v1/audit-logs` | `permission:audit.view` | query `branch_id`/`session_id` (nuevos, opcionales, UUID) | `200` igual que hoy + `branch_id`/`session_id` como campos propios de cada registro (columnas indexadas, no dentro de `properties`) | `403` sin permiso |

No se agregan rutas nuevas: el contrato de `/api/v1/profile/sessions` cambia de forma (nuevos campos `device_type`/`browser`/`platform`, se quita `user_agent` crudo y `expires_at`), lo cual es una **ruptura de contrato intencional** — se coordina con el frontend en el mismo spec (§8), no hay otros consumidores externos documentados.

---
## 5. Permisos y seguridad

- Sin cambios en permisos: `profile/*` sigue requiriendo solo `JwtAuthenticate` (autenticado), `audit-logs` sigue requiriendo `audit.view`.
- Refuerzo de seguridad real: hoy, revocar "las demás sesiones" no impedía que una rotación **en vuelo** (dentro del periodo de gracia) siguiera produciendo tokens válidos para un dispositivo que se acababa de revocar; con el `lockForUpdate()` sobre `sessions` y la comprobación `$session->isActive()` en `rotateRefreshToken()`, una sesión revocada no puede rotar más, haya o no una rotación concurrente en curso.
- `token_hash`/`csrf_token_hash` siguen sin exponerse en ningún Resource (invariante ya cumplida, se verifica con test de regresión en §9).

---
## 6. Migraciones

### 6.1 Reemplazar `sessions` (framework, sin uso) por `sessions` (dominio)
`back-laravel/database/migrations/2026_10_24_000001_create_sessions_table.php`

**Corrección de diagnóstico respecto a la primera versión de este documento:** `0001_01_01_000000_create_users_table.php` (el *stub* por defecto de Laravel) **ya crea** una tabla `sessions` — es la tabla de sesiones HTTP del framework (`id` string, `payload`, `last_activity`), la misma que referencia `config/session.php`. No está vacía por falta de migración, sino porque ningún request real pasa por el grupo de middleware `web`/`StartSession` (confirmado al ejecutar esta migración contra `postgres-test`: falló con `relation "sessions" already exists"` hasta agregar el `dropIfExists` de abajo). La migración de este spec **reemplaza** esa tabla por la de dominio, bajo el mismo nombre:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La tabla `sessions` de framework (ver nota arriba) nunca recibe filas por el flujo
        // normal de la API JWT stateless; se descarta su esquema y se recrea con el de dominio.
        Schema::dropIfExists('sessions');

        Schema::create('sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_type', 20)->default('unknown');
            $table->string('browser', 50)->nullable();
            $table->string('platform', 50)->nullable();
            $table->timestampTz('last_used_at');
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'revoked_at'], 'idx_sessions_user_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
```

### 6.2 Agregar `session_id` a `refresh_tokens` (nullable) y backfill
`back-laravel/database/migrations/2026_10_24_000002_add_session_id_to_refresh_tokens_table.php`

```php
<?php

use App\Modules\Auth\Models\Session;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->uuid('session_id')->nullable()->after('user_id');
        });

        // Backfill: no existe información histórica para reconstruir con certeza qué filas
        // revocadas pertenecían a la misma sesión lógica (ip_address/user_agent nunca
        // se pensaron como clave de agrupación). Se crea una sesión 1:1 por cada refresh
        // token EXISTENTE (activo o no), preservando ip/user_agent/created_at tal cual.
        // A partir de este punto, la rotación sí preserva session_id correctamente.
        DB::table('refresh_tokens')->orderBy('id')->chunkById(500, function ($tokens) {
            foreach ($tokens as $token) {
                $sessionId = (string) \Illuminate\Support\Str::uuid7();

                DB::table('sessions')->insert([
                    'id' => $sessionId,
                    'user_id' => $token->user_id,
                    'ip_address' => $token->ip_address,
                    'user_agent' => $token->user_agent,
                    'device_type' => \App\Support\Auth\UserAgentParser::parse($token->user_agent)['device_type'],
                    'browser' => \App\Support\Auth\UserAgentParser::parse($token->user_agent)['browser'],
                    'platform' => \App\Support\Auth\UserAgentParser::parse($token->user_agent)['platform'],
                    'last_used_at' => $token->created_at,
                    'expires_at' => $token->expires_at,
                    'revoked_at' => $token->revoked_at,
                    'created_at' => $token->created_at,
                    'updated_at' => $token->updated_at,
                ]);

                DB::table('refresh_tokens')->where('id', $token->id)->update(['session_id' => $sessionId]);
            }
        });

        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->uuid('session_id')->nullable(false)->change();
            $table->foreign('session_id')->references('id')->on('sessions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('session_id');
        });
    }
};
```

**Limitación documentada a propósito** (no se oculta): este backfill trata cada fila de `refresh_tokens` existente como el inicio de su propia sesión, incluidas las ya revocadas por rotación. El efecto práctico es que las sesiones históricas quedan "fragmentadas" (una por rotación pasada), pero **a partir de este deploy** toda nueva rotación sí preserva `session_id` correctamente — el problema que motiva este spec (US-1) queda resuelto hacia adelante, que es lo que importa para el objetivo de negocio; reconstruir el pasado con certeza no es posible porque el dato de agrupación nunca se capturó.

### 6.3 Quitar `ip_address`/`user_agent` de `refresh_tokens`
`back-laravel/database/migrations/2026_10_24_000003_drop_device_info_from_refresh_tokens_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent']);
        });
    }

    public function down(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('session_id');
            $table->text('user_agent')->nullable()->after('ip_address');
        });
    }
};
```

Se dividen en 3 migraciones (crear → backfill+FK → drop) y no se edita `2026_10_13_205900_create_refresh_tokens_table.php` ni `2026_10_15_000001_add_device_info_to_refresh_tokens_table.php` (ya aplicadas), siguiendo la regla del repo de no tocar migraciones existentes.

### 6.4 Corregir el `morphMap` duplicado de `CashSession`
`back-laravel/app/Modules/Finance/Providers/FinanceServiceProvider.php` — quitar la clave `'CashSession'`, dejar solo `'cash_session'`:

```php
Relation::morphMap([
    'cash_session' => CashSession::class, // antes también registraba 'CashSession' => CashSession::class (duplicado, alias muerto)
    'cash_movement' => CashMovement::class,
    'cash_transfer' => CashTransfer::class,
    'bank_operation' => BankOperation::class,
    'bank_movement' => BankMovement::class,
], merge: true);
```

**Nota de migración de datos:** las filas ya existentes en `activity_log` con `subject_type = 'CashSession'` quedan como están (son datos históricos válidos; Eloquent sigue resolviéndolas porque `Relation::getMorphedModel()` sigue reconociendo esa clave para lectura aunque ya no se use para escritura — Spatie no reescribe `subject_type` de logs pasados). No se requiere migración de datos.

### 6.5 Registrar los modelos ausentes de `morphMap`

Se agrega un alias `snake_case` para cada modelo con `LogsActivity` que hoy cae a FQCN:

- `back-laravel/app/Modules/Users/Providers/UsersServiceProvider.php` (si no existe, se crea igual que los demás módulos): `'user' => User::class`.
- `back-laravel/app/Modules/Sales/Providers/SalesServiceProvider.php`: agregar `'document_series' => DocumentSeries::class`.
- `back-laravel/app/Modules/Finance/Providers/FinanceServiceProvider.php`: agregar `'bank_account' => BankAccount::class`, `'exchange_rate' => ExchangeRate::class`.
- `back-laravel/app/Modules/Purchases/Providers/PurchasesServiceProvider.php`: agregar `'purchase_request' => PurchaseRequest::class`, `'provider_price' => ProviderPrice::class`.

El test de regresión de §9 (T-9) falla si se agrega un modelo nuevo con `LogsActivity` sin alias — así este catálogo no se vuelve a desactualizar silenciosamente.

### 6.6 `branch_id`/`session_id` como columnas propias e indexadas de `activity_log`
`back-laravel/database/migrations/2026_10_24_000004_add_branch_id_and_session_id_to_activity_log_table.php`

Decisión posterior a la primera entrega del spec (a pedido del dueño del producto, para auditar a escala): `branch_id`/`session_id` dejan de vivir dentro de `properties` (JSON, sin índice) y pasan a ser columnas `uuid` nullable con índice compuesto `(columna, created_at)` — el patrón de consulta real de auditoría es "toda la actividad de esta sede/sesión, más reciente primero".

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('activitylog.database_connection');
        $table = config('activitylog.table_name');

        Schema::connection($connection)->table($table, function (Blueprint $blueprint) {
            $blueprint->uuid('branch_id')->nullable()->after('causer_id');
            $blueprint->uuid('session_id')->nullable()->after('branch_id');

            $blueprint->index(['branch_id', 'created_at'], 'idx_activity_log_branch');
            $blueprint->index(['session_id', 'created_at'], 'idx_activity_log_session');
        });

        // Backfill desde filas existentes que guardaban estas claves dentro de `properties`
        // (convención anterior a este cambio). Se valida el formato UUID antes del cast para
        // no abortar la migración si alguna fila histórica tuviera un valor no válido.
        $uuidPattern = '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$';

        DB::connection($connection)->statement(
            "UPDATE \"{$table}\" SET branch_id = (properties::jsonb->>'branch_id')::uuid
             WHERE properties::jsonb->>'branch_id' ~ ?",
            [$uuidPattern]
        );

        DB::connection($connection)->statement(
            "UPDATE \"{$table}\" SET session_id = (properties::jsonb->>'session_id')::uuid
             WHERE properties::jsonb->>'session_id' ~ ?",
            [$uuidPattern]
        );
    }

    public function down(): void
    {
        $connection = config('activitylog.database_connection');
        $table = config('activitylog.table_name');

        Schema::connection($connection)->table($table, function (Blueprint $blueprint) {
            $blueprint->dropIndex('idx_activity_log_branch');
            $blueprint->dropIndex('idx_activity_log_session');
            $blueprint->dropColumn(['branch_id', 'session_id']);
        });
    }
};
```

**No hay FK**: a propósito, `branch_id`/`session_id` son columnas indexadas sin `foreignUuid()`/constraint — igual que `causer_id`/`subject_id` (morph, tampoco llevan FK). `sessions` se poda regularmente (`auth:prune-tokens`, §3) y las sedes nunca deberían bloquear su borrado por filas de auditoría; una FK con `cascadeOnDelete()` borraría historia de auditoría al podar una sesión vieja (justo lo que no se quiere), y con `restrictOnDelete()` el prune fallaría. Sin FK, el historial sobrevive siempre, que es el propósito de una tabla de auditoría.

**No se limpia `properties` de filas históricas**: la columna es `json`, no `jsonb` — borrar una clave requiere un cast adicional, y el valor duplicado en `properties` de filas anteriores a esta migración es inofensivo (nadie vuelve a leerlo de ahí). Verificado manualmente insertando una fila con el formato antiguo y corriendo el backfill contra `laravel_dev`: `UPDATE 1` en ambas columnas, valores coincidentes.

`AuditLogResource`, `ListAuditLogsRequest` y `AuditLogService` (§4) leen/filtran directamente estas columnas (`where('branch_id', ...)`, `where('session_id', ...)`), ya no `where('properties->session_id', ...)`.

---
## 7. Configuración e infraestructura

`config/session.php` (`SESSION_DRIVER`): **corrección respecto a la primera versión de esta sección.** Dejar `SESSION_DRIVER=database` y solo documentar que "no aplica" no es suficiente: `database/seeders/StockDemoSeeder.php` llama `auth()->login($admin)` para que un seeder de datos demo tenga un `auth()->user()` válido (fallback del causer implícito de Spatie). `Auth::login()` en el guard por defecto de Laravel toca el almacén de sesión **aunque no haya ningún middleware `web` ni request HTTP de por medio** — no es un caso exclusivo de requests. Con `SESSION_DRIVER=database` apuntando a la tabla `sessions` de dominio (esquema UUID, sin `payload`/`last_activity`), esto rompía `php artisan migrate:fresh --seed` (`make migrate-fresh`) con `SQLSTATE[22P02]: invalid input syntax for type uuid` al intentar borrar una fila de `sessions` usando un id de sesión de Laravel (string aleatorio), no un UUID.

**Decisión final:** cambiar `SESSION_DRIVER` a `array` en `back-laravel/.env`, `back-laravel/.env.example` y en los 3 servicios (`back-app`, `back-queue`, `back-scheduler`) de `docker-compose.yml` y `docker-compose.prod.yml` — alineado con `docker-compose.test.yml`, que **ya** usaba `array` (por eso el problema nunca apareció en `make test`, solo en `make migrate-fresh` contra el entorno dev). Con `array`, cualquier código que toque el almacén de sesión (hoy solo ese seeder; potencialmente cualquier futuro) usa un store en memoria por proceso, sin tocar la tabla `sessions` de dominio bajo ninguna circunstancia — cierra la clase de bug completa, no solo el caso de requests HTTP. Se deja además una nota en el propio `config/session.php` explicando el porqué, para que no se revierta a `database` sin leer esto:

```php
// NOTA (spec 033): esta configuración es la de sesiones HTTP de Laravel (cookie de sesión +
// tabla `sessions`). Este proyecto es una API JWT stateless: no hay grupo de middleware
// `web`/`StartSession` activo en routes/api.php. La tabla `sessions` original de framework
// (creada por 0001_01_01_000000_create_users_table.php) fue reemplazada por la migración
// 2026_10_24_000001_create_sessions_table.php con el esquema de dominio de
// App\Modules\Auth\Models\Session (sesiones de usuario/dispositivo) — esquema incompatible
// con el que espera el driver `database` de Laravel (id string, payload, last_activity).
// Por eso SESSION_DRIVER=array en .env/.env.example y en los 3 servicios de
// docker-compose.yml/docker-compose.prod.yml: no basta con que no haya middleware `web` en
// la API, porque Auth::login() en el guard por defecto toca el almacén de sesión igual fuera
// de un request HTTP (se reprodujo en database/seeders/StockDemoSeeder.php vía
// auth()->login($admin), ejecutado por `php artisan migrate:fresh --seed` / `make
// migrate-fresh`: fallaba con "invalid input syntax for type uuid" al borrar una fila de
// `sessions` usando un id de sesión de Laravel, no un UUID). Si en el futuro se necesita el
// driver de sesión HTTP de Laravel para algo (p. ej. un panel administrativo con `web`),
// usar una tabla aparte con otro nombre (`http_sessions`, vía `php artisan session:table`),
// no reutilizar `sessions` ni volver a `SESSION_DRIVER=database`.
```

No se agregan variables de entorno nuevas más allá del valor de `SESSION_DRIVER` ya existente (el claim `sid` no necesita configuración; reutiliza `jwt.secret`/`jwt.algorithm` existentes).

---
## 8. Frontend

### 8.1 `front-vue/src/modules/profile/stores/profileStore.js`
El *shape* de cada sesión cambia de `{id, ip_address, user_agent, created_at, expires_in, is_current}` a `{id, ip_address, device_type, browser, platform, created_at, last_used_at, is_current}`. El store solo necesita ajustar el *mapper* si existe uno (o ningún cambio si pasa el payload tal cual — verificar en la tarea de implementación).

### 8.2 `front-vue/src/modules/profile/components/SessionsTab.vue`
Se elimina `getDeviceIcon(userAgent)`/`getBrowserName(userAgent)` (regex sobre `user_agent` crudo) y se consumen los campos ya resueltos por el backend:

```html
<VAvatar size="38" :color="session.is_current ? 'primary' : 'secondary'" variant="tonal">
  <VIcon size="22" :icon="deviceIcon(session.device_type)" />
</VAvatar>
<div>
  <div class="font-weight-medium">
    {{ session.browser || 'Navegador web' }}
    <span v-if="session.platform" class="text-caption text-medium-emphasis">· {{ session.platform }}</span>
  </div>
</div>
```

```js
const deviceIcon = deviceType => ({
  mobile: 'tabler-device-mobile',
  tablet: 'tabler-device-tablet',
  desktop: 'tabler-device-laptop',
}[deviceType] ?? 'tabler-device-unknown')
```

La columna "INICIO DE SESIÓN" pasa a mostrar `session.created_at` (ahora estable, no se resetea en cada rotación) y se agrega una columna/subtexto "Última actividad" con `session.last_used_at` (antes no existía ningún campo que distinguiera ambos).

No se migra `VTable` a `ServerDataTable` en este spec (listado acotado — máximo unas pocas decenas de sesiones por usuario, sin paginación de servidor ni filtros — se deja como nota en Decisiones Rechazadas §10, no como regresión introducida por este spec).

---
## 9. Testing

Backend (`back-laravel/tests/Feature/Modules/Auth/` y `.../Audit/`), ejecutables con `make test-filter FILTER=<Test>`:

- `SessionLifecycleTest` — login crea 1 `Session` + 1 `RefreshToken`; N rotaciones consecutivas preservan `session_id` y no crean sesiones nuevas; `last_used_at`/`ip_address`/`user_agent` de la sesión se actualizan en cada rotación.
- `SessionRevocationTest` — `logout` revoca la sesión y su refresh token activo; `revokeOtherSessions` revoca todas menos la actual (verificado con `lockForUpdate`, test con dos revocaciones concurrentes simuladas no produce duplicados ni excepción de deadlock); un intento de rotar un refresh token de una sesión ya revocada responde `401` aunque la fila de `refresh_tokens` todavía esté dentro de su periodo de gracia.
- `UpdatePasswordRevokesAllSessionsTest` — cambiar contraseña revoca todas las sesiones del usuario (no solo los refresh tokens sueltos).
- `ActivityBranchAndSessionContextTest` — `branch_id`/`session_id` se resuelven como columnas propias de `Activity` (no dentro de `properties`): una mutación dentro de una request autenticada deja `session_id` poblado; una actividad generada en un job (sin `AuditContext` *bound*) sobre un modelo con columna `branch_id` propia (p. ej. `Sale`) sí lleva `branch_id`, replicando el caso real de `SendElectronicDocumentJob`.
- `AuditLogTest::test_audit_logs_can_be_filtered_by_session_id` / `test_audit_logs_can_be_filtered_by_branch_id` — el filtro de `/api/v1/audit-logs` consulta las columnas indexadas directamente.
- `MorphMapIntegrityTest` (T-9, regresión transversal) — recorre `Relation::morphMap()` acumulado de todos los `ServiceProvider`s y falla si dos claves apuntan a la misma clase, o si algún modelo con `use LogsActivity` no tiene alias registrado (usa reflexión sobre `app_path('Modules')` para enumerar modelos con el trait).
- `ManualActivityCausedByTest` — los 5 sitios migrados (`InstallmentScheduleService`, `StockQueryService`, `UserBranchService`, `PurchaseOrderWorkflowService`, `RoleService`) producen logs con `causer_id` no nulo cuando se invocan con un causer explícito.

Frontend: `cd front-vue && npm run lint`; no se agregan pruebas unitarias nuevas de componente para `SessionsTab.vue` (no hay precedente de tests de componente en `profile/` — se mantiene consistente con el resto del módulo).

---
## 10. Decisiones Rechazadas

- **Guardar `device_fingerprint` (hash de atributos del navegador) para detectar robo de sesión.** Fuera de alcance: el proyecto no tiene hoy ningún mecanismo de fingerprinting y agregarlo aquí mezclaría una feature de seguridad avanzada con el refactor de datos solicitado. Si se quiere, es un spec propio.
- **Usar una librería de parseo de user-agent (`whichbrowser/parser` u otra).** Se descarta: el caso de uso es solo mostrar un icono/nombre amigable en "Mis dispositivos", no detección de compatibilidad ni seguridad; una dependencia nueva no se justifica (ver §1.3).
- **Resolver `session_id` en `Activity::booted()` consultando `RefreshToken`/`Session` por el `jti` del access token.** Se descarta por una consulta extra en cada escritura de log; el claim `sid` ya viaja en el token validado y no requiere I/O adicional.
- **Migrar automáticamente el histórico de `refresh_tokens` reconstruyendo cadenas de rotación por heurística (ip/user_agent + proximidad temporal).** Se descarta por ser una heurística frágil que daría una falsa sensación de certeza sobre datos que nunca se capturaron con ese propósito; se prefiere la limitación explícita documentada en §6.2.
- **Mover `SessionsTab.vue` a `ServerDataTable`.** Se descarta en este spec: la lista no pagina ni filtra en servidor (es inherentemente pequeña, sesiones de un solo usuario); forzar `ServerDataTable` agregaría complejidad sin beneficio real. Se deja como nota para `.agents/rules/frontend-ui.md` si en el futuro se decide que toda lista, sin excepción, debe usarlo.
- **Reabrir y completar el spec 007-audit-trail (pasarlo a `Done`) dentro de este spec.** Se descarta: 007 documentó la instalación inicial y sigue siendo la referencia histórica correcta de "cómo se instaló"; este spec 033 es el que añade gobernanza/estandarización y se referencia como su continuación, sin reescribir su contenido original.
