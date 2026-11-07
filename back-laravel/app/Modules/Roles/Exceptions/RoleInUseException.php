<?php

declare(strict_types=1);

namespace App\Modules\Roles\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleInUseException extends Exception
{
    public function __construct(int $usersCount = 1)
    {
        parent::__construct("El rol tiene {$usersCount} usuario(s) asignado(s).");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], 409);
    }
}
