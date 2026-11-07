<?php

declare(strict_types=1);

namespace App\Modules\Finance\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class InsufficientCashException extends Exception
{
    public function __construct(string $message = 'Efectivo insuficiente en caja.')
    {
        parent::__construct($message, 422);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'amount' => [$this->getMessage()],
            ],
        ], 422);
    }
}
