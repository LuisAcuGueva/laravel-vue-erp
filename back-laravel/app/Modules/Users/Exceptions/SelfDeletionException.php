<?php

declare(strict_types=1);

namespace App\Modules\Users\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class SelfDeletionException extends Exception
{
    public function __construct(string $message = 'No puede eliminar su propia cuenta de usuario.')
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
