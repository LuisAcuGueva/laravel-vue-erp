<?php

declare(strict_types=1);

namespace App\Modules\Finance\Data;

use App\Modules\Finance\Enums\MoneyDirection;
use App\Modules\Finance\Enums\SystemConcept;
use App\Modules\Users\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Datos para liquidar un documento que mueve dinero (pago, cobro, reembolso)
 * en caja o en banco según el `settlement` del método de pago.
 */
final readonly class SettlementData
{
    public function __construct(
        public string $paymentMethodId,
        public MoneyDirection $direction,
        public string $amount,
        public string $currencyId,
        public Model $source,
        public string $sourceNumber,
        public ?string $branchId = null,
        public ?string $cashSessionId = null,
        public SystemConcept|string|null $concept = null,
        public ?string $conceptId = null,
        public ?string $reference = null,
        public ?string $description = null,
        public ?string $counterpartyName = null,
        public ?DateTimeInterface $movementDate = null,
        public ?User $user = null,
    ) {}
}
