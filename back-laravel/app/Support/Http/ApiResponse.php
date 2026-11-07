<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Operación exitosa',
        int $code = 200,
        array $meta = []
    ): JsonResponse {
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

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function createdResponse(
        mixed $data = null,
        string $message = 'Recurso creado exitosamente',
        array $meta = []
    ): JsonResponse {
        return $this->successResponse($data, $message, 201, $meta);
    }

    /**
     * @param  array<string, mixed>|null  $errors
     */
    protected function errorResponse(
        string $message,
        int $code = 400,
        ?array $errors = null
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ];

        return response()->json($payload, $code);
    }

    protected function noContentResponse(): JsonResponse
    {
        return response()->json(null, 204);
    }
}
