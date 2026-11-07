<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Finance\Data\SettlementData;
use App\Modules\Finance\Enums\CashSessionStatus;
use App\Modules\Finance\Enums\MoneyDirection;
use App\Modules\Finance\Enums\Settlement;
use App\Modules\Finance\Enums\SystemConcept;
use App\Modules\Finance\Models\CashSession;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Finance\Services\CashSessionSummaryService;
use App\Modules\Finance\Services\MoneySettlementService;
use App\Modules\Sales\Enums\CreditNoteSettlementMode;
use App\Modules\Sales\Enums\InstallmentStatus;
use App\Modules\Sales\Models\CreditNote;
use App\Modules\Sales\Models\CreditNoteInstallmentAdjustment;
use App\Modules\Sales\Models\ReceivableInstallment;
use App\Modules\Sales\Models\Sale;
use App\Modules\Users\Models\User;
use App\Support\Pricing\Decimal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class CreditNoteSettlementService
{
    public function __construct(
        private readonly MoneySettlementService $settlementService,
        private readonly CashSessionSummaryService $cashSessionSummaryService,
    ) {}

    /**
     * Aplica el monto de la nota de crédito reduciendo cuotas/saldo de la venta y/o reembolsando dinero.
     *
     * @param  array{
     *     mode?: string|CreditNoteSettlementMode,
     *     payment_method_id?: string|null,
     *     cash_session_id?: string|null,
     *     reference?: string|null
     * }  $settlement
     */
    public function apply(CreditNote $note, array $settlement, ?User $user = null): void
    {
        /** @var Sale $sale */
        $sale = Sale::whereKey($note->sale_id)->lockForUpdate()->firstOrFail();

        $rawMode = $settlement['mode'] ?? 'auto';
        $mode = $rawMode instanceof CreditNoteSettlementMode ? $rawMode : CreditNoteSettlementMode::from((string) $rawMode);

        $totalNote = (string) $note->total;
        $saleBalance = (string) $sale->balance;

        if ($mode === CreditNoteSettlementMode::AUTO) {
            $toBalance = Decimal::cmp($saleBalance, '0.00', 2) > 0
                ? (Decimal::cmp($totalNote, $saleBalance, 2) <= 0 ? $totalNote : $saleBalance)
                : '0.00';
            $refund = Decimal::sub($totalNote, $toBalance, 2);
        } else {
            $toBalance = '0.00';
            $refund = $totalNote;
        }

        // 1. Amortización de cuotas pendientes desde la última
        if (Decimal::cmp($toBalance, '0.00', 2) > 0) {
            /** @var Collection<int, ReceivableInstallment> $openInstallments */
            $openInstallments = ReceivableInstallment::query()
                ->where('sale_id', $sale->id)
                ->whereIn('status', [InstallmentStatus::Pending, InstallmentStatus::PartiallyPaid])
                ->where('balance', '>', 0)
                ->orderBy('number', 'desc')
                ->lockForUpdate()
                ->get();

            $remainingToBalance = $toBalance;

            foreach ($openInstallments as $installment) {
                if (Decimal::cmp($remainingToBalance, '0.00', 2) <= 0) {
                    break;
                }

                $instBalance = (string) $installment->balance;
                $alloc = Decimal::cmp($remainingToBalance, $instBalance, 2) < 0
                    ? $remainingToBalance
                    : $instBalance;

                CreditNoteInstallmentAdjustment::create([
                    'credit_note_id' => $note->id,
                    'receivable_installment_id' => $installment->id,
                    'amount' => $alloc,
                ]);

                $newPaid = Decimal::add((string) $installment->paid_amount, $alloc, 2);
                $newBal = Decimal::sub((string) $installment->balance, $alloc, 2);
                $newStatus = Decimal::cmp($newBal, '0.00', 2) <= 0
                    ? InstallmentStatus::Paid
                    : InstallmentStatus::PartiallyPaid;

                $installment->update([
                    'paid_amount' => $newPaid,
                    'balance' => $newBal,
                    'status' => $newStatus,
                ]);

                $remainingToBalance = Decimal::sub($remainingToBalance, $alloc, 2);
            }

            // Reducir balance de la venta
            $newSaleBalance = Decimal::sub($saleBalance, $toBalance, 2);
            $sale->update([
                'balance' => $newSaleBalance,
            ]);
        }

        // 2. Reembolso financiero (si aplica)
        $paymentMethodId = $settlement['payment_method_id'] ?? null;
        $cashSessionId = $settlement['cash_session_id'] ?? null;
        $reference = $settlement['reference'] ?? null;

        if (Decimal::cmp($refund, '0.00', 2) > 0) {
            if (! $paymentMethodId) {
                throw ValidationException::withMessages([
                    'settlement.payment_method_id' => 'Debe especificar el método de pago para el reembolso de dinero.',
                ]);
            }

            /** @var PaymentMethod|null $paymentMethod */
            $paymentMethod = PaymentMethod::whereKey($paymentMethodId)->first();
            if ($paymentMethod === null || ! $paymentMethod->is_active) {
                throw ValidationException::withMessages([
                    'settlement.payment_method_id' => 'El método de pago no existe o no está activo.',
                ]);
            }

            if ($paymentMethod->settlement === Settlement::CashBox) {
                if (! $cashSessionId) {
                    throw ValidationException::withMessages([
                        'settlement.cash_session_id' => 'Debe especificar la sesión de caja para el reembolso en efectivo.',
                    ]);
                }

                /** @var CashSession|null $cashSession */
                $cashSession = CashSession::whereKey($cashSessionId)->lockForUpdate()->first();
                if ($cashSession === null || $cashSession->status !== CashSessionStatus::Open) {
                    throw ValidationException::withMessages([
                        'settlement.cash_session_id' => 'La sesión de caja seleccionada no está abierta.',
                    ]);
                }

                $availableCash = $this->cashSessionSummaryService->availableCash($cashSession);
                if (Decimal::cmp($refund, $availableCash, 2) > 0) {
                    throw ValidationException::withMessages([
                        'settlement.cash_session_id' => "Efectivo insuficiente en caja para el reembolso (disponible: {$availableCash}, reembolso: {$refund}).",
                    ]);
                }
            }

            $this->settlementService->settle(new SettlementData(
                paymentMethodId: $paymentMethod->id,
                direction: MoneyDirection::Out,
                amount: $refund,
                currencyId: $note->currency_id,
                source: $note,
                sourceNumber: $note->full_number,
                branchId: $note->branch_id,
                cashSessionId: $cashSessionId,
                concept: SystemConcept::SaleRefund,
                reference: $reference,
                description: "Reembolso por Nota de Crédito {$note->full_number} a venta {$sale->full_number}",
                counterpartyName: $note->client_name,
                movementDate: Carbon::parse($note->issue_date)->startOfDay(),
                user: $user,
            ));
        }

        // 3. Actualizar registro de la Nota de Crédito
        $note->update([
            'applied_to_balance' => $toBalance,
            'refunded_amount' => $refund,
            'settlement_mode' => $mode,
            'refund_payment_method_id' => Decimal::cmp($refund, '0.00', 2) > 0 ? $paymentMethodId : null,
            'refund_cash_session_id' => Decimal::cmp($refund, '0.00', 2) > 0 ? $cashSessionId : null,
        ]);
    }

    /**
     * Revierte la liquidación de la nota de crédito (reembolso y amortización de cuotas).
     */
    public function revert(CreditNote $note, ?User $user = null): void
    {
        // 1. Revertir movimientos financieros si hubo reembolso
        if (Decimal::cmp((string) $note->refunded_amount, '0.00', 2) > 0) {
            $this->settlementService->unsettle(
                source: $note,
                sourceNumber: $note->full_number,
                reason: 'Anulación de nota de crédito',
                createdBy: $user?->id
            );
        }

        // 2. Restaurar cuotas afectadas y balance de la venta
        if (Decimal::cmp((string) $note->applied_to_balance, '0.00', 2) > 0) {
            $adjustments = $note->installmentAdjustments()->get();

            foreach ($adjustments as $adj) {
                /** @var ReceivableInstallment $inst */
                $inst = ReceivableInstallment::whereKey($adj->receivable_installment_id)->lockForUpdate()->firstOrFail();

                $newPaid = Decimal::sub((string) $inst->paid_amount, (string) $adj->amount, 2);
                $newBal = Decimal::add((string) $inst->balance, (string) $adj->amount, 2);
                $newStatus = Decimal::cmp($newPaid, '0.00', 2) <= 0
                    ? InstallmentStatus::Pending
                    : InstallmentStatus::PartiallyPaid;

                $inst->update([
                    'paid_amount' => $newPaid,
                    'balance' => $newBal,
                    'status' => $newStatus,
                ]);
            }

            /** @var Sale $sale */
            $sale = Sale::whereKey($note->sale_id)->lockForUpdate()->firstOrFail();
            $newSaleBal = Decimal::add((string) $sale->balance, (string) $note->applied_to_balance, 2);
            $sale->update([
                'balance' => $newSaleBal,
            ]);
        }
    }
}
