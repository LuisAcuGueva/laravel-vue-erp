<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Clients\Models\Client;
use App\Modules\Configuration\Services\SystemParameterService;
use App\Modules\Sales\Enums\InstallmentStatus;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Models\ReceivableInstallment;
use App\Modules\Sales\Models\Sale;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class CreditPolicyService
{
    public function __construct(
        private readonly SystemParameterService $parameterService,
    ) {}

    /**
     * Calcula la deuda vigente de un cliente sumando los saldos pendientes de sus ventas emitidas.
     */
    public function debt(Client $client): string
    {
        $debt = (float) Sale::query()
            ->where('client_id', $client->id)
            ->where('status', SaleStatus::Issued->value)
            ->sum('balance');

        return number_format($debt, 2, '.', '');
    }

    /**
     * Retorna la línea de crédito disponible para nuevas ventas.
     */
    public function available(Client $client): string
    {
        if ($client->is_system || (float) $client->credit_limit <= 0) {
            return '0.00';
        }

        $available = (float) $client->credit_limit - (float) $this->debt($client);

        return number_format(max(0, $available), 2, '.', '');
    }

    /**
     * Valida si el cliente tiene cuotas vencidas impagas.
     */
    public function hasOverdueInstallments(Client $client): bool
    {
        return ReceivableInstallment::query()
            ->where('client_id', $client->id)
            ->where('status', '!=', InstallmentStatus::Cancelled)
            ->where('balance', '>', 0)
            ->where('due_date', '<', Carbon::today()->toDateString())
            ->exists();
    }

    /**
     * Valida que el cliente pueda tomar un nuevo crédito por el monto especificado.
     *
     * @throws ValidationException
     */
    public function assertAvailable(Client $client, string $amount): void
    {
        if ($client->is_system) {
            throw ValidationException::withMessages([
                'client_id' => 'El cliente genérico no tiene permitido comprar al crédito.',
            ]);
        }

        $blockWhenOverdue = (bool) $this->parameterService->get('sales.block_credit_when_overdue', true);
        if ($blockWhenOverdue && $this->hasOverdueInstallments($client)) {
            throw ValidationException::withMessages([
                'payment_condition' => 'El cliente tiene cuotas vencidas.',
            ]);
        }

        $limit = (float) $client->credit_limit;
        if ($limit <= 0) {
            throw ValidationException::withMessages([
                'client_id' => 'El cliente no cuenta con una línea de crédito autorizada.',
            ]);
        }

        $debt = (float) $this->debt($client);
        $available = $limit - $debt;

        if ((float) $amount > $available) {
            $formattedAvailable = number_format(max(0, $available), 2, '.', '');
            throw ValidationException::withMessages([
                'payment_condition' => "Crédito insuficiente: disponible S/ {$formattedAvailable}.",
            ]);
        }
    }
}
