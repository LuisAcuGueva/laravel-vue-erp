<?php

declare(strict_types=1);

namespace App\Modules\Finance\Data;

use App\Modules\Finance\Enums\MoneyDirection;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

final readonly class BankMovementData
{
    public function __construct(
        public string $bankAccountId,
        public MoneyDirection|string $direction,
        public ?string $conceptId = null,
        public ?string $conceptCode = null,
        public float|string $amount = 0.0,
        public ?DateTimeInterface $movementDate = null,
        public ?Model $source = null,
        public ?string $sourceNumber = null,
        public ?string $operationNumber = null,
        public ?string $paymentMethodId = null,
        public ?string $description = null,
        public ?string $counterpartyName = null,
        public ?string $voucherPath = null,
        public ?string $branchId = null,
        public bool $isManual = false,
        public ?string $createdBy = null,
    ) {}
}
