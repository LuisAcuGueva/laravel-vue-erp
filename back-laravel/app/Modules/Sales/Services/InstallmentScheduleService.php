<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Clients\Models\Client;
use App\Modules\Sales\Enums\InstallmentStatus;
use App\Modules\Sales\Enums\PaymentCondition;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Models\ReceivableInstallment;
use App\Modules\Sales\Models\Sale;
use App\Modules\Users\Models\User;
use App\Support\Pricing\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InstallmentScheduleService
{
    /**
     * Construye el cronograma de cuotas para una venta al crédito.
     *
     * @param  array<int, array{due_date: string, amount: numeric-string|float|int}>|null  $explicit
     * @return array<int, array{number: int, due_date: string, amount: string}>
     */
    public function build(Sale $sale, ?array $explicit = null, ?int $count = null, ?int $intervalDays = null): array
    {
        if ($sale->payment_condition !== PaymentCondition::Credit) {
            return [];
        }

        $balance = (string) $sale->balance;
        if (Decimal::cmp($balance, '0.00', 2) <= 0) {
            return [];
        }

        // 1. Cronograma explícito enviado por el cliente/frontend
        if ($explicit !== null && count($explicit) > 0) {
            $schedule = [];
            foreach (array_values($explicit) as $i => $row) {
                $schedule[] = [
                    'number' => $i + 1,
                    'due_date' => (string) $row['due_date'],
                    'amount' => number_format((float) $row['amount'], 2, '.', ''),
                ];
            }

            return $schedule;
        }

        // 2. Reparto en N cuotas iguales cada X días
        if ($count !== null && $count > 1) {
            $interval = $intervalDays !== null && $intervalDays > 0 ? $intervalDays : 30;
            $baseAmount = Decimal::div($balance, (string) $count, 2);
            $schedule = [];
            $sumPrevious = '0.00';

            for ($k = 1; $k < $count; $k++) {
                $schedule[] = [
                    'number' => $k,
                    'due_date' => $sale->issue_date->copy()->addDays($k * $interval)->toDateString(),
                    'amount' => $baseAmount,
                ];
                $sumPrevious = Decimal::add($sumPrevious, $baseAmount, 2);
            }

            // La última cuota absorbe el redondeo
            $lastAmount = Decimal::sub($balance, $sumPrevious, 2);
            $schedule[] = [
                'number' => $count,
                'due_date' => $sale->issue_date->copy()->addDays($count * $interval)->toDateString(),
                'amount' => $lastAmount,
            ];

            return $schedule;
        }

        // 3. Cuota única al vencimiento de la venta o a 30 días
        $dueDate = $sale->due_date !== null
            ? $sale->due_date->toDateString()
            : $sale->issue_date->copy()->addDays(30)->toDateString();

        return [
            [
                'number' => 1,
                'due_date' => $dueDate,
                'amount' => number_format((float) $balance, 2, '.', ''),
            ],
        ];
    }

    /**
     * Valida que el cronograma cumpla con la suma exacta del saldo y los límites de crédito del cliente.
     *
     * @param  Sale|array<string, mixed>  $saleContext
     * @param  array<int, array{due_date: string, amount: string|float|int}>  $schedule
     *
     * @throws ValidationException
     */
    public function validate(Sale|array $saleContext, array $schedule, Client $client): void
    {
        if (empty($schedule)) {
            return;
        }

        $issueDate = $saleContext instanceof Sale
            ? $saleContext->issue_date
            : Carbon::parse($saleContext['issue_date'] ?? now());

        $balance = $saleContext instanceof Sale
            ? (string) $saleContext->balance
            : (string) ($saleContext['balance'] ?? '0.00');

        $creditDays = (int) ($client->credit_days ?? 30);

        // Validar suma exacta
        $sum = '0.00';
        foreach ($schedule as $row) {
            $sum = Decimal::add($sum, (string) $row['amount'], 2);
        }

        if (Decimal::cmp($sum, $balance, 2) !== 0) {
            throw ValidationException::withMessages([
                'installments' => "La suma de las cuotas ({$sum}) debe ser igual al saldo de la venta ({$balance}).",
            ]);
        }

        // Validar fechas de vencimiento
        foreach ($schedule as $idx => $row) {
            $dueDate = Carbon::parse($row['due_date']);

            if ($dueDate->lt($issueDate)) {
                throw ValidationException::withMessages([
                    "installments.{$idx}.due_date" => 'La fecha de vencimiento de la cuota no puede ser anterior a la fecha de emisión.',
                ]);
            }

            if ($creditDays > 0 && $dueDate->gt($issueDate->copy()->addDays($creditDays))) {
                throw ValidationException::withMessages([
                    "installments.{$idx}.due_date" => "El plazo de la cuota ({$dueDate->toDateString()}) excede los {$creditDays} días de crédito permitidos para el cliente.",
                ]);
            }
        }
    }

    /**
     * Persiste las cuotas calculadas para la venta en la base de datos.
     *
     * @param  array<int, array{number: int, due_date: string, amount: string}>  $schedule
     */
    public function createFor(Sale $sale, array $schedule): void
    {
        foreach ($schedule as $row) {
            ReceivableInstallment::create([
                'sale_id' => $sale->id,
                'client_id' => $sale->client_id,
                'currency_id' => $sale->currency_id,
                'number' => $row['number'],
                'due_date' => $row['due_date'],
                'amount' => $row['amount'],
                'paid_amount' => '0.00',
                'balance' => $row['amount'],
                'status' => InstallmentStatus::Pending,
            ]);
        }
    }

    /**
     * Reprograma las cuotas pendientes de una venta conservando las ya pagadas o parcialmente pagadas.
     *
     * @param  array<int, array{due_date: string, amount: numeric-string|float|int}>  $newSchedule
     *
     * @throws ValidationException
     */
    public function reschedule(Sale $sale, array $newSchedule, User $user): void
    {
        if (empty($newSchedule)) {
            throw ValidationException::withMessages([
                'installments' => 'Debe ingresar al menos una cuota para la reprogramación.',
            ]);
        }

        DB::transaction(function () use ($sale, $newSchedule, $user): void {
            /** @var Sale $lockedSale */
            $lockedSale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($lockedSale->status === SaleStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'sale' => 'No se pueden reprogramar cuotas de una venta anulada.',
                ]);
            }

            // Bloquear cuotas existentes
            $existingInstallments = ReceivableInstallment::where('sale_id', $lockedSale->id)
                ->lockForUpdate()
                ->orderBy('number')
                ->get();

            // Saldo pendiente total de la venta
            $pendingBalance = (string) $lockedSale->balance;

            // Validar que la nueva lista sume exactamente el saldo pendiente de la venta
            $newSum = '0.00';
            foreach ($newSchedule as $row) {
                $newSum = Decimal::add($newSum, (string) $row['amount'], 2);
            }

            if (Decimal::cmp($newSum, $pendingBalance, 2) !== 0) {
                throw ValidationException::withMessages([
                    'installments' => "La suma de las nuevas cuotas ({$newSum}) debe ser igual al saldo pendiente de la venta ({$pendingBalance}).",
                ]);
            }

            // Cuotas que se eliminarán: pendientes sin asignaciones de cobro
            $toDelete = $existingInstallments->filter(function (ReceivableInstallment $i) {
                return $i->status === InstallmentStatus::Pending && $i->allocations()->doesntExist();
            });

            // Guardar historial para el activity log
            $previousScheduleData = $existingInstallments->map(fn (ReceivableInstallment $i) => [
                'number' => $i->number,
                'due_date' => $i->due_date->toDateString(),
                'amount' => $i->amount,
                'balance' => $i->balance,
                'status' => $i->status->value,
            ])->all();

            foreach ($toDelete as $installment) {
                $installment->delete();
            }

            // Numeración continua a partir de las cuotas conservadas
            $remaining = $existingInstallments->reject(fn ($i) => $toDelete->contains('id', $i->id));
            $startNumber = (int) ($remaining->max('number') ?? 0) + 1;

            $created = [];
            foreach (array_values($newSchedule) as $idx => $row) {
                $installmentNumber = $startNumber + $idx;
                $formattedAmount = number_format((float) $row['amount'], 2, '.', '');

                $inst = ReceivableInstallment::create([
                    'sale_id' => $lockedSale->id,
                    'client_id' => $lockedSale->client_id,
                    'currency_id' => $lockedSale->currency_id,
                    'number' => $installmentNumber,
                    'due_date' => (string) $row['due_date'],
                    'amount' => $formattedAmount,
                    'paid_amount' => '0.00',
                    'balance' => $formattedAmount,
                    'status' => InstallmentStatus::Pending,
                ]);

                $created[] = [
                    'number' => $inst->number,
                    'due_date' => $inst->due_date->toDateString(),
                    'amount' => $inst->amount,
                ];
            }

            // Registrar log de actividad
            activity()
                ->performedOn($lockedSale)
                ->causedBy($user)
                ->event('schedule_reprogrammed')
                ->withProperties([
                    'previous_schedule' => $previousScheduleData,
                    'new_schedule' => $created,
                ])
                ->log('Reprogramación de cuotas');
        });
    }
}
