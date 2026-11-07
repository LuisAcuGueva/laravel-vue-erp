<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Clients\Models\Client;
use App\Modules\Finance\Data\SettlementData;
use App\Modules\Finance\Enums\CashSessionStatus;
use App\Modules\Finance\Enums\MoneyDirection;
use App\Modules\Finance\Enums\PaymentMethodKind;
use App\Modules\Finance\Enums\SystemConcept;
use App\Modules\Finance\Models\CashSession;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Finance\Services\BoxAccessService;
use App\Modules\Finance\Services\MoneySettlementService;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Sales\Enums\CollectionStatus;
use App\Modules\Sales\Enums\InstallmentStatus;
use App\Modules\Sales\Models\Collection;
use App\Modules\Sales\Models\CollectionAllocation;
use App\Modules\Sales\Models\ReceivableInstallment;
use App\Modules\Sales\Models\Sale;
use App\Modules\Users\Models\User;
use App\Support\Branch\ActiveBranch;
use App\Support\Branch\BranchAccessGuard;
use App\Support\Numbering\SequentialCodeGenerator;
use App\Support\Pricing\Decimal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CollectionService
{
    public function __construct(
        private readonly MoneySettlementService $settlement,
        private readonly BranchAccessGuard $branchAccessGuard,
        private readonly BoxAccessService $boxAccessService,
    ) {}

    /**
     * Registra una cobranza y aplica amortizaciones a las cuotas y comprobantes correspondientes.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create(array $data, User $user, ?Branch $branch = null): Collection
    {
        $activeBranch = $branch ?? app(ActiveBranch::class)->get();
        $this->branchAccessGuard->assertAccessible($user, $activeBranch->id);

        /** @var Client $client */
        $client = Client::whereKey($data['client_id'])->firstOrFail();
        $currencyId = (string) $data['currency_id'];

        $paymentDateStr = $data['payment_date'] ?? $data['collection_date'] ?? Carbon::today()->toDateString();
        $paymentDate = Carbon::parse((string) $paymentDateStr);

        $amountRaw = $data['amount'] ?? $data['total_amount'] ?? null;
        if ($amountRaw === null && ! empty($data['payments'])) {
            $amountRaw = (string) array_sum(array_column($data['payments'], 'amount'));
        }

        $totalAmount = number_format((float) ($amountRaw ?? 0), 2, '.', '');
        if (Decimal::cmp($totalAmount, '0.00', 2) <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'El monto total a cobrar debe ser mayor a 0.',
            ]);
        }

        // Determinar método de pago, caja o cuenta
        $paymentMethodId = (string) ($data['payment_method_id'] ?? ($data['payments'][0]['payment_method_id'] ?? ''));
        if ($paymentMethodId === '') {
            throw ValidationException::withMessages([
                'payment_method_id' => 'Debe seleccionar un método de pago.',
            ]);
        }

        /** @var PaymentMethod $pm */
        $pm = PaymentMethod::whereKey($paymentMethodId)->firstOrFail();
        $cashSessionId = $data['cash_session_id'] ?? ($data['payments'][0]['cash_session_id'] ?? null);
        $bankAccountId = $data['bank_account_id'] ?? ($data['payments'][0]['bank_account_id'] ?? null);

        if ($pm->kind === PaymentMethodKind::Cash) {
            if (! $cashSessionId) {
                throw ValidationException::withMessages([
                    'cash_session_id' => 'Debe indicar una sesión de caja abierta para cobros en efectivo.',
                ]);
            }
            /** @var CashSession $session */
            $session = CashSession::whereKey($cashSessionId)->firstOrFail();
            if ($session->status !== CashSessionStatus::Open) {
                throw ValidationException::withMessages([
                    'cash_session_id' => 'La sesión de caja seleccionada no se encuentra abierta.',
                ]);
            }
            if ($session->box !== null && ! $this->boxAccessService->canOperate($user, $session->box)) {
                throw ValidationException::withMessages([
                    'cash_session_id' => 'No tiene autorización para operar esta caja.',
                ]);
            }
        }

        $autoAllocate = (bool) ($data['auto_allocate'] ?? false);
        /** @var array<int, array{receivable_installment_id?: string, installment_id?: string, amount: numeric-string|float|int}> $rawAllocations */
        $rawAllocations = $data['allocations'] ?? [];

        if (! $autoAllocate && empty($rawAllocations)) {
            throw ValidationException::withMessages([
                'allocations' => 'Debe indicar las cuotas a amortizar o activar la asignación automática.',
            ]);
        }

        return DB::transaction(function () use (
            $activeBranch,
            $client,
            $currencyId,
            $paymentDate,
            $paymentMethodId,
            $cashSessionId,
            $bankAccountId,
            $totalAmount,
            $autoAllocate,
            $rawAllocations,
            $data,
            $user
        ): Collection {
            $number = SequentialCodeGenerator::next('collection', 'CB');

            /** @var array<int, array{installment: ReceivableInstallment, amount: string}> $allocationsToApply */
            $allocationsToApply = [];

            if ($autoAllocate) {
                /** @var \Illuminate\Database\Eloquent\Collection<int, ReceivableInstallment> $openInstallments */
                $openInstallments = ReceivableInstallment::query()
                    ->where('client_id', $client->id)
                    ->where('currency_id', $currencyId)
                    ->whereIn('status', [InstallmentStatus::Pending, InstallmentStatus::PartiallyPaid])
                    ->where('balance', '>', 0)
                    ->orderBy('due_date', 'asc')
                    ->orderBy('number', 'asc')
                    ->lockForUpdate()
                    ->get();

                $totalAvailableDebt = '0.00';
                foreach ($openInstallments as $inst) {
                    $totalAvailableDebt = Decimal::add($totalAvailableDebt, (string) $inst->balance, 2);
                }

                if (Decimal::cmp($totalAmount, $totalAvailableDebt, 2) > 0) {
                    throw ValidationException::withMessages([
                        'amount' => "El monto a cobrar ({$totalAmount}) supera la deuda total pendiente ({$totalAvailableDebt}).",
                    ]);
                }

                $remaining = $totalAmount;
                foreach ($openInstallments as $inst) {
                    if (Decimal::cmp($remaining, '0.00', 2) <= 0) {
                        break;
                    }

                    $balanceStr = (string) $inst->balance;
                    $allocAmount = Decimal::cmp($remaining, $balanceStr, 2) < 0 ? $remaining : $balanceStr;
                    $allocationsToApply[] = [
                        'installment' => $inst,
                        'amount' => $allocAmount,
                    ];
                    $remaining = Decimal::sub($remaining, $allocAmount, 2);
                }
            } else {
                $allocationsSum = '0.00';

                foreach ($rawAllocations as $idx => $row) {
                    $rowAmount = number_format((float) ($row['amount'] ?? 0), 2, '.', '');
                    if (Decimal::cmp($rowAmount, '0.00', 2) <= 0) {
                        throw ValidationException::withMessages([
                            "allocations.{$idx}.amount" => 'El monto asignado a cada cuota debe ser mayor a 0.',
                        ]);
                    }

                    $instId = $row['receivable_installment_id'] ?? $row['installment_id'] ?? null;
                    if (! $instId) {
                        throw ValidationException::withMessages([
                            "allocations.{$idx}.receivable_installment_id" => 'Debe indicar la cuota a amortizar.',
                        ]);
                    }

                    /** @var ReceivableInstallment $inst */
                    $inst = ReceivableInstallment::query()
                        ->whereKey($instId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($inst->client_id !== $client->id) {
                        throw ValidationException::withMessages([
                            "allocations.{$idx}.receivable_installment_id" => 'La cuota seleccionada no pertenece al cliente.',
                        ]);
                    }

                    if ($inst->currency_id !== $currencyId) {
                        throw ValidationException::withMessages([
                            "allocations.{$idx}.receivable_installment_id" => 'La cuota seleccionada tiene una moneda distinta a la cobranza.',
                        ]);
                    }

                    if ($inst->status === InstallmentStatus::Cancelled || $inst->status === InstallmentStatus::Paid) {
                        throw ValidationException::withMessages([
                            "allocations.{$idx}.receivable_installment_id" => 'La cuota seleccionada ya se encuentra pagada o fue anulada.',
                        ]);
                    }

                    if (Decimal::cmp($rowAmount, (string) $inst->balance, 2) > 0) {
                        throw ValidationException::withMessages([
                            "allocations.{$idx}.amount" => "El monto ({$rowAmount}) excede el saldo pendiente de la cuota ({$inst->balance}).",
                        ]);
                    }

                    $allocationsSum = Decimal::add($allocationsSum, $rowAmount, 2);
                    $allocationsToApply[] = [
                        'installment' => $inst,
                        'amount' => $rowAmount,
                    ];
                }

                if (Decimal::cmp($allocationsSum, $totalAmount, 2) !== 0) {
                    throw ValidationException::withMessages([
                        'allocations' => "La suma de las amortizaciones ({$allocationsSum}) debe ser igual al total cobrado ({$totalAmount}).",
                    ]);
                }
            }

            // 1. Crear registro de Cobranza
            $collection = Collection::create([
                'number' => $number,
                'branch_id' => $activeBranch->id,
                'client_id' => $client->id,
                'payment_date' => $paymentDate->toDateString(),
                'currency_id' => $currencyId,
                'payment_method_id' => $paymentMethodId,
                'cash_session_id' => $cashSessionId,
                'bank_account_id' => $bankAccountId,
                'amount' => $totalAmount,
                'reference' => $data['reference'] ?? ($data['payments'][0]['reference'] ?? null),
                'notes' => $data['notes'] ?? null,
                'status' => CollectionStatus::Completed,
                'created_by' => $user->id,
            ]);

            // 2. Crear asignaciones y actualizar saldos de cuotas y ventas
            foreach ($allocationsToApply as $alloc) {
                $inst = $alloc['installment'];
                $amount = $alloc['amount'];

                CollectionAllocation::create([
                    'collection_id' => $collection->id,
                    'receivable_installment_id' => $inst->id,
                    'sale_id' => $inst->sale_id,
                    'amount' => $amount,
                ]);

                // Actualizar cuota
                $newInstPaid = Decimal::add((string) $inst->paid_amount, $amount, 2);
                $newInstBalance = Decimal::sub((string) $inst->balance, $amount, 2);
                $newInstStatus = Decimal::cmp($newInstBalance, '0.00', 2) <= 0
                    ? InstallmentStatus::Paid
                    : InstallmentStatus::PartiallyPaid;

                $inst->update([
                    'paid_amount' => $newInstPaid,
                    'balance' => $newInstBalance,
                    'status' => $newInstStatus,
                ]);

                // Actualizar venta
                /** @var Sale $sale */
                $sale = Sale::whereKey($inst->sale_id)->lockForUpdate()->firstOrFail();
                $newSalePaid = Decimal::add((string) $sale->paid_amount, $amount, 2);
                $newSaleBalance = Decimal::sub((string) $sale->balance, $amount, 2);

                $sale->update([
                    'paid_amount' => $newSalePaid,
                    'balance' => $newSaleBalance,
                ]);
            }

            // 3. Liquidar movimientos financieros de caja o banco
            $this->settlement->settle(new SettlementData(
                paymentMethodId: $paymentMethodId,
                direction: MoneyDirection::In,
                amount: $totalAmount,
                currencyId: $currencyId,
                source: $collection,
                sourceNumber: $collection->number,
                branchId: $activeBranch->id,
                cashSessionId: $cashSessionId,
                concept: SystemConcept::ReceivableCollection,
                reference: $collection->reference,
                description: "Cobranza {$collection->number} - {$client->name}",
                counterpartyName: $client->name,
                movementDate: $paymentDate,
                user: $user,
            ));

            return $collection->fresh([
                'allocations.installment',
                'allocations.sale',
                'client',
                'currency',
                'paymentMethod',
                'branch',
            ]) ?? $collection;
        });
    }

    /**
     * Anula una cobranza, revirtiendo movimientos de caja/banco y restituyendo saldos en cuotas y ventas.
     *
     * @throws ValidationException
     */
    public function cancel(Collection $collection, string $reason, User $user): Collection
    {
        return DB::transaction(function () use ($collection, $reason, $user): Collection {
            /** @var Collection $locked */
            $locked = Collection::whereKey($collection->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== CollectionStatus::Completed) {
                throw ValidationException::withMessages([
                    'status' => 'Solo se pueden anular cobranzas en estado completado.',
                ]);
            }

            // 1. Revertir movimientos financieros
            $this->settlement->unsettle($locked, $locked->number, $reason, $user->id);

            // 2. Revertir amortizaciones en cuotas y ventas
            $allocations = $locked->allocations()->get();

            foreach ($allocations as $alloc) {
                /** @var ReceivableInstallment $inst */
                $inst = ReceivableInstallment::whereKey($alloc->receivable_installment_id)->lockForUpdate()->firstOrFail();

                $revertedPaid = Decimal::sub((string) $inst->paid_amount, (string) $alloc->amount, 2);
                $revertedBalance = Decimal::add((string) $inst->balance, (string) $alloc->amount, 2);
                $revertedStatus = Decimal::cmp($revertedPaid, '0.00', 2) <= 0
                    ? InstallmentStatus::Pending
                    : InstallmentStatus::PartiallyPaid;

                $inst->update([
                    'paid_amount' => $revertedPaid,
                    'balance' => $revertedBalance,
                    'status' => $revertedStatus,
                ]);

                /** @var Sale $sale */
                $sale = Sale::whereKey($alloc->sale_id)->lockForUpdate()->firstOrFail();
                $revertedSalePaid = Decimal::sub((string) $sale->paid_amount, (string) $alloc->amount, 2);
                $revertedSaleBalance = Decimal::add((string) $sale->balance, (string) $alloc->amount, 2);

                $sale->update([
                    'paid_amount' => $revertedSalePaid,
                    'balance' => $revertedSaleBalance,
                ]);
            }

            // 3. Actualizar estado de la cobranza
            $locked->update([
                'status' => CollectionStatus::Cancelled,
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            return $locked->fresh([
                'allocations.installment',
                'allocations.sale',
                'client',
                'currency',
                'paymentMethod',
                'branch',
            ]) ?? $locked;
        });
    }
}
