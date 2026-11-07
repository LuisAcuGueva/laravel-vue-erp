<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureRequestId
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id');

        if (empty($requestId) || ! is_string($requestId)) {
            $requestId = (string) Str::uuid();
            $request->headers->set('X-Request-Id', $requestId);
        }

        Log::withContext(['request_id' => $requestId]);

        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
