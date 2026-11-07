<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class InventoryRuleException extends Exception
{
    protected string $field;

    public function __construct(string $message, string $field = 'inventory')
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
