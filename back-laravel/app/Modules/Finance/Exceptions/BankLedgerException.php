<?php

declare(strict_types=1);

namespace App\Modules\Finance\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class BankLedgerException extends Exception
{
    public function __construct(string $message, int $code = 422)
    {
        parent::__construct($message, $code);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'bank' => [$this->getMessage()],
            ],
        ], $this->getCode() ?: 422);
    }
}
