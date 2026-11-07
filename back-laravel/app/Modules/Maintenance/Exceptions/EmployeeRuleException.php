<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class EmployeeRuleException extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message, 422);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'employee' => [$this->getMessage()],
            ],
        ], 422);
    }
}
