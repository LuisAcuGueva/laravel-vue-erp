<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class InsufficientStockException extends Exception
{
    /**
     * @param  array{catalog_item_id: string, warehouse_id: string, available: float, required: float}|array{}  $meta
     */
    public function __construct(
        string $message,
        protected array $meta = [],
        int $code = 422
    ) {
        parent::__construct($message, $code);
    }

    /**
     * @return array{catalog_item_id: string, warehouse_id: string, available: float, required: float}|array{}
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'items' => [$this->getMessage()],
            ],
            'meta' => $this->meta,
        ], 422);
    }
}
