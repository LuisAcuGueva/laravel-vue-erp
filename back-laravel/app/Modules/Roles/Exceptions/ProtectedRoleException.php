<?php

declare(strict_types=1);

namespace App\Modules\Roles\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProtectedRoleException extends Exception
{
    public function __construct(string $roleName = 'admin')
    {
        parent::__construct("El rol {$roleName} es de sistema y no puede modificarse.");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], 422);
    }
}
