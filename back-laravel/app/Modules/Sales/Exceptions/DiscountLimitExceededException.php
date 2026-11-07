<?php

declare(strict_types=1);

namespace App\Modules\Sales\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class DiscountLimitExceededException extends Exception
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        string $message,
        private readonly array $errors = []
    ) {
        parent::__construct($message, 422);
    }

    /**
     * @return array<string, list<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => $this->errors,
        ], 422);
    }
}
