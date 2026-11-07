<?php

declare(strict_types=1);

namespace App\Modules\Sales\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class SalesRuleException extends Exception
{
    protected string $field;

    public function __construct(string $message, string $field = 'sales')
    {
        parent::__construct($message, 422);
        $this->field = $field;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                $this->field => [$this->getMessage()],
            ],
        ], 422);
    }
}
