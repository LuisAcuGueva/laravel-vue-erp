<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureRequestId;
use App\Http\Middleware\ResolveActiveBranch;
use App\Http\Middleware\SecurityHeaders;
use App\Modules\Auth\Middleware\JwtAuthenticate;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append([
            EnsureRequestId::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'jwt' => JwtAuthenticate::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'branch' => ResolveActiveBranch::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e): bool {
            return $request->is('api/*') || $request->expectsJson();
        });

        // 1. Error de Validación (422)
        $exceptions->render(function (ValidationException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        });

        // 2. Permiso denegado Spatie (403)
        $exceptions->render(function (UnauthorizedException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes los permisos necesarios para realizar esta acción.',
                'errors' => null,
            ], 403);
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes los permisos necesarios para realizar esta acción.',
                'errors' => null,
            ], 403);
        });

        // 3. No autenticado / JWT inválido (401)
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'No autenticado o sesión expirada.',
                'errors' => null,
            ], 401);
        });

        // 4. Recurso no encontrado (404)
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'El recurso solicitado no existe.',
                'errors' => null,
            ], 404);
        });

        // 5. Rate limiting excedido (429)
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            $retryAfter = $e->getHeaders()['Retry-After'] ?? 60;

            return response()->json([
                'success' => false,
                'message' => 'Demasiadas solicitudes. Por favor intente más tarde.',
                'retry_after' => (int) $retryAfter,
            ], 429);
        });

        // 6. Excepciones no controladas (500)
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                if (! app()->isProduction() && config('app.debug')) {
                    return null;
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Ha ocurrido un error interno en el servidor.',
                    'request_id' => $request->header('X-Request-Id'),
                ], 500);
            }

            return null;
        });

        // Asegurar encabezado X-Request-Id en cualquier respuesta de excepción
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $requestId = $request->header('X-Request-Id');
            if ($requestId && ! $response->headers->has('X-Request-Id')) {
                $response->headers->set('X-Request-Id', $requestId);
            }

            return $response;
        });
    })->create();
