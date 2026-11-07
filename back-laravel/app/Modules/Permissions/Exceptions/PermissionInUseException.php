<?php

declare(strict_types=1);

namespace App\Modules\Permissions\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionInUseException extends Exception
{
    public function __construct(int $rolesCount = 1)
    {
        parent::__construct("El permiso está asignado a {$rolesCount} rol(es).");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], 409);
    }
}
