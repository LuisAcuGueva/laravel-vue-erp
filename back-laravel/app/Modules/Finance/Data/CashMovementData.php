<?php

declare(strict_types=1);

namespace App\Modules\Finance\Data;

use App\Modules\Finance\Enums\MoneyDirection;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

final readonly class CashMovementData
{
    public function __construct(
        public string $sessionId,
        public MoneyDirection|string $direction,
        public ?string $conceptId = null,
        public ?string $conceptCode = null,
        public ?string $paymentMethodId = null,
        public ?string $paymentMethodCode = null,
        public float|string $amount = 0.0,
        public ?Model $source = null,
        public ?string $sourceNumber = null,
        public ?string $reference = null,
        public ?string $description = null,
        public ?string $counterpartyName = null,
        public bool $isManual = false,
        public ?string $createdBy = null,
        public ?DateTimeInterface $movedAt = null,
    ) {}
}
