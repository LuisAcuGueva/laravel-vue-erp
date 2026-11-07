# 005 · api-resilience-and-exceptions — Design
> **Spec:** 005 · **Alcance:** back · **Módulo:** Transversal · **Prioridad:** alta · **Depende de specs:** —
> **Status:** Done
> **Última actualización:** 2026-10-14
> **Autores:** Luis Acuña Guevara

---
## 1. Manejador Global de Excepciones (`bootstrap/app.php`)

Laravel 12 centraliza el manejo de excepciones en `bootstrap/app.php` a través del closure `withExceptions`:

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*') || $request->expectsJson());

    // 1. Error de Validación (422)
    $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
        return response()->json([
            'success' => false,
            'message' => 'Los datos proporcionados no son válidos.',
            'errors' => $e->errors(),
        ], 422);
    });

    // 2. Permiso denegado Spatie (403)
    $exceptions->render(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, $request) {
        return response()->json([
            'success' => false,
            'message' => 'No tienes los permisos necesarios para realizar esta acción.',
            'errors' => null,
        ], 403);
    });

    // 3. No autenticado / JWT inválido (401)
    $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
        return response()->json([
            'success' => false,
            'message' => 'No autenticado o sesión expirada.',
            'errors' => null,
        ], 401);
    });

    // 4. Recurso no encontrado (404)
    $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
        return response()->json([
            'success' => false,
            'message' => 'El recurso solicitado no existe.',
            'errors' => null,
        ], 404);
    });

    // 5. Rate limiting excedido (429)
    $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, $request) {
        return response()->json([
            'success' => false,
            'message' => 'Demasiadas solicitudes. Por favor intente más tarde.',
            'retry_after' => $e->getHeaders()['Retry-After'] ?? 60,
        ], 429);
    });

    // 6. Excepciones no controladas (500)
    $exceptions->render(function (\Throwable $e, $request) {
        if (! app()->isProduction() && config('app.debug')) {
            return null; // Deja que Laravel muestre detalle en desarrollo local
        }
        return response()->json([
            'success' => false,
            'message' => 'Ha ocurrido un error interno en el servidor.',
            'request_id' => $request->header('X-Request-Id'),
        ], 500);
    });
})
```

## 2. Trait `ApiResponse`
Ubicación: `back-laravel/app/Support/Http/ApiResponse.php`

```php
namespace App\Support\Http;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function successResponse(mixed $data = null, string $message = 'Operación exitosa', int $code = 200, array $meta = []): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if (! empty($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $code);
    }

    protected function errorResponse(string $message, int $code = 400, ?array $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }
}
```

## 3. Middleware de Trazabilidad y Seguridad

### 3.1 `EnsureRequestId`
Ubicación: `back-laravel/app/Http/Middleware/EnsureRequestId.php`
- Detecta o genera un UUIDv7 (`Str::uuid()`).
- Agrega al contexto de logging: `Log::withContext(['request_id' => $requestId])`.
- Establece la cabecera en la respuesta: `$response->headers->set('X-Request-Id', $requestId)`.

### 3.2 `SecurityHeaders`
Ubicación: `back-laravel/app/Http/Middleware/SecurityHeaders.php`
- Inyecta cabeceras `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`.

## 4. Rate Limiting en `bootstrap/app.php` y `routes/api.php`
Configuración del limitador en `AppServiceProvider` o `bootstrap/app.php`:
```php
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
});

RateLimiter::for('login', function (Request $request) {
    $key = 'login.'.($request->input('email') ?: $request->ip());
    return Limit::perMinute(5)->by($key);
});
```

## 5. Refactor de Tipado UUID en Controladores
Corregir en `ClientController.php`:
```diff
- public function show(int|string $id): JsonResponse|ClientResource
+ public function show(string $id): JsonResponse|ClientResource

- public function update(int|string $id, ClientRequest $request): JsonResponse|ClientResource
+ public function update(string $id, ClientRequest $request): JsonResponse|ClientResource

- public function destroy(int|string $id): JsonResponse
+ public function destroy(string $id): JsonResponse
```
(Y análogamente en `ClientService.php`, `MakeModuleCommand.php` y futuros controladores generados).
