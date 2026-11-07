<?php

declare(strict_types=1);

namespace App\Modules\Finance\Data;

/**
 * Problema detectado en la configuración contable de Finanzas.
 */
final readonly class AccountingSetupIssue
{
    public const ERROR = 'error';

    public const WARNING = 'warning';

    /**
     * @param  list<array{id: string, code: string, name: string}>  $items  Máximo 50 ítems.
     */
    public function __construct(
        public string $code,
        public string $severity,
        public string $message,
        public int $count,
        public array $items,
    ) {}

    /**
     * @return array{code: string, severity: string, message: string, count: int, items: list<array{id: string, code: string, name: string}>}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'severity' => $this->severity,
            'message' => $this->message,
            'count' => $this->count,
            'items' => $this->items,
        ];
    }
}
