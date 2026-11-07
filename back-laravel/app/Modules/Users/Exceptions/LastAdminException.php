<?php

declare(strict_types=1);

namespace App\Modules\Users\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class LastAdminException extends Exception
{
    public function __construct(string $message = 'No se puede realizar esta acción sobre el último administrador del sistema.')
    {
        parent::__construct($message, 422);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], 422);
    }
}
