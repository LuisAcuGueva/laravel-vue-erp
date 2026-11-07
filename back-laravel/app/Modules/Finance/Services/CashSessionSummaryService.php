<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Enums\CashTransferStatus;
use App\Modules\Finance\Models\CashSession;
use App\Modules\Finance\Models\FinanceConcept;
use App\Modules\Finance\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;

class CashSessionSummaryService
{
    public function expectedCash(CashSession $session): string
    {
        /** @var object{cash_in: string|null, cash_out: string|null}|null $totals */
        $totals = DB::table('cash_movements')
            ->where('cash_session_id', $session->id)
            ->where('affects_cash', true)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END), 0) as cash_in,
                COALESCE(SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END), 0) as cash_out
            ")
            ->first();

        $cashIn = $totals !== null ? (float) $totals->cash_in : 0.0;
        $cashOut = $totals !== null ? (float) $totals->cash_out : 0.0;
        $opening = (float) $session->opening_amount;

        $expected = $opening + $cashIn - $cashOut;

        return number_format($expected, 2, '.', '');
    }

    public function availableCash(CashSession $session, ?string $excludeTransferId = null): string
    {
        $expected = (float) $this->expectedCash($session);

        $pendingQuery = DB::table('cash_transfers')
            ->where('source_session_id', $session->id)
            ->where('status', CashTransferStatus::Pending->value);

        if ($excludeTransferId !== null) {
            $pendingQuery->where('id', '!=', $excludeTransferId);
        }

        $pendingOut = (float) $pendingQuery->sum('amount');

        $available = max(0.0, $expected - $pendingOut);

        return number_format($available, 2, '.', '');
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(CashSession $session): array
    {
        /** @var object{cash_in: string|null, cash_out: string|null}|null $totals */
        $totals = DB::table('cash_movements')
            ->where('cash_session_id', $session->id)
            ->where('affects_cash', true)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END), 0) as cash_in,
                COALESCE(SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END), 0) as cash_out
            ")
            ->first();

        $cashIn = $totals !== null ? (float) $totals->cash_in : 0.0;
        $cashOut = $totals !== null ? (float) $totals->cash_out : 0.0;
        $opening = (float) $session->opening_amount;
        $expected = $opening + $cashIn - $cashOut;

        // Agrupación por método de pago
        $methodRows = DB::table('cash_movements')
            ->where('cash_session_id', $session->id)
            ->selectRaw("
                payment_method_id,
                COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END), 0) as total_in,
                COALESCE(SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END), 0) as total_out
            ")
            ->groupBy('payment_method_id')
            ->get();

        $methodIds = $methodRows->pluck('payment_method_id')->filter()->all();
        $methods = PaymentMethod::whereIn('id', $methodIds)->get()->keyBy('id');

        $byPaymentMethod = [];
        foreach ($methodRows as $row) {
            /** @var PaymentMethod|null $method */
            $method = $methods->get($row->payment_method_id);
            $totalIn = (float) $row->total_in;
            $totalOut = (float) $row->total_out;

            $byPaymentMethod[] = [
                'method' => [
                    'id' => $row->payment_method_id,
                    'code' => $method !== null ? $method->code : 'UNKNOWN',
                    'name' => $method !== null ? $method->name : 'Desconocido',
                ],
                'in' => $totalIn,
                'out' => $totalOut,
                'net' => (float) number_format($totalIn - $totalOut, 2, '.', ''),
            ];
        }

        // Agrupación por concepto
        $conceptRows = DB::table('cash_movements')
            ->where('cash_session_id', $session->id)
            ->selectRaw("
                concept_id,
                COALESCE(SUM(amount), 0) as total,
                COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END), 0) as total_in,
                COALESCE(SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END), 0) as total_out
            ")
            ->groupBy('concept_id')
            ->get();

        $conceptIds = $conceptRows->pluck('concept_id')->filter()->all();
        $concepts = FinanceConcept::whereIn('id', $conceptIds)->get()->keyBy('id');

        $byConcept = [];
        foreach ($conceptRows as $row) {
            /** @var FinanceConcept|null $concept */
            $concept = $concepts->get($row->concept_id);
            $totalIn = (float) $row->total_in;
            $totalOut = (float) $row->total_out;

            // La dirección mostrada refleja el movimiento de caja real de la sesión,
            // no la clasificación contable del concepto (p. ej. los conceptos de
            // transferencia se imputan a la cuenta puente 103 pero cada uno mueve
            // caja en un solo sentido: depósito/transferencia enviada = egreso,
            // retiro/transferencia recibida = ingreso).
            $directionStr = match (true) {
                $totalIn > 0.0 && $totalOut <= 0.0 => 'income',
                $totalOut > 0.0 && $totalIn <= 0.0 => 'expense',
                $concept !== null && $concept->direction instanceof \BackedEnum => $concept->direction->value,
                $concept !== null => (string) $concept->direction,
                default => 'income',
            };

            $byConcept[] = [
                'concept' => [
                    'id' => $row->concept_id,
                    'code' => $concept !== null ? $concept->code : 'UNKNOWN',
                    'name' => $concept !== null ? $concept->name : 'Desconocido',
                ],
                'direction' => $directionStr,
                'total' => (float) $row->total,
            ];
        }

        return [
            'opening_amount' => $opening,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'expected_cash' => (float) number_format($expected, 2, '.', ''),
            'available_cash' => (float) $this->availableCash($session),
            'counted_cash' => $session->counted_cash !== null ? (float) $session->counted_cash : null,
            'difference' => $session->difference !== null ? (float) $session->difference : null,
            'review_status' => $session->review_status instanceof \BackedEnum
                ? $session->review_status->value
                : (string) $session->review_status,
            'by_payment_method' => $byPaymentMethod,
            'by_concept' => $byConcept,
        ];
    }
}
