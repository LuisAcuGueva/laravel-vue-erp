<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Data;

use App\Modules\Inventory\Enums\MovementType;
use Illuminate\Database\Eloquent\Model;

final readonly class StockMovementData
{
    public function __construct(
        public string $warehouseId,
        public string $catalogItemId,
        public float $baseQuantity,          // > 0, en unidad base
        public MovementType $type,
        public Model $source,                // documento de origen (nota, compra, venta…)
        public string $sourceNumber,
        public ?float $unitCost = null,      // obligatorio en entradas (costo por unidad base)
        public ?string $presentationId = null,
        public ?float $presentationQuantity = null,
        public ?string $reasonCode = null,   // SystemReason o código de motivo
        public ?string $notes = null,
        public ?\DateTimeInterface $movedAt = null,
    ) {}
}
