<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\KardexMovement;
use App\Modules\Inventory\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KardexQueryService
{
    /**
     * Calcula el saldo inicial al inicio de la fecha dada.
     *
     * @return array{quantity: float, unit_cost: float, total: float}
     */
    public function openingBalance(string $warehouseId, string $catalogItemId, Carbon $from): array
    {
        /** @var KardexMovement|null $lastMovement */
        $lastMovement = KardexMovement::where('warehouse_id', $warehouseId)
            ->where('catalog_item_id', $catalogItemId)
            ->where('moved_at', '<', $from->copy()->startOfDay())
            ->orderByDesc('moved_at')
            ->orderByDesc('created_at')
            ->first();

        if ($lastMovement === null) {
            return [
                'quantity' => 0.0,
                'unit_cost' => 0.0,
                'total' => 0.0,
            ];
        }

        return [
            'quantity' => (float) $lastMovement->balance_quantity,
            'unit_cost' => (float) $lastMovement->balance_unit_cost,
            'total' => (float) $lastMovement->balance_total_cost,
        ];
    }

    /**
     * Retorna los movimientos dentro del período ordenados cronológicamente.
     *
     * @return Collection<int, KardexMovement>
     */
    public function movements(string $warehouseId, string $catalogItemId, Carbon $from, Carbon $to): Collection
    {
        return KardexMovement::where('warehouse_id', $warehouseId)
            ->where('catalog_item_id', $catalogItemId)
            ->whereBetween('moved_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->with(['reason:id,code,name', 'user:id,name', 'presentation:id,name,factor'])
            ->orderBy('moved_at')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Retorna el resumen completo del kardex: saldo inicial, movimientos y saldo final.
     *
     * @return array{
     *     opening_balance: array{quantity: float, unit_cost: float, total: float},
     *     movements: Collection<int, KardexMovement>,
     *     closing_balance: array{quantity: float, unit_cost: float, total: float}
     * }
     */
    public function summary(string $warehouseId, string $catalogItemId, Carbon $from, Carbon $to): array
    {
        $opening = $this->openingBalance($warehouseId, $catalogItemId, $from);
        $movements = $this->movements($warehouseId, $catalogItemId, $from, $to);

        if ($movements->isNotEmpty()) {
            /** @var KardexMovement $last */
            $last = $movements->last();
            $closing = [
                'quantity' => (float) $last->balance_quantity,
                'unit_cost' => (float) $last->balance_unit_cost,
                'total' => (float) $last->balance_total_cost,
            ];
        } else {
            $closing = $opening;
        }

        return [
            'opening_balance' => $opening,
            'movements' => $movements,
            'closing_balance' => $closing,
        ];
    }

    /**
     * Transmite el kardex en formato CSV con BOM UTF-8.
     */
    public function streamCsv(string $warehouseId, string $catalogItemId, Carbon $from, Carbon $to): StreamedResponse
    {
        /** @var Warehouse $warehouse */
        $warehouse = Warehouse::findOrFail($warehouseId);
        /** @var CatalogItem $item */
        $item = CatalogItem::findOrFail($catalogItemId);

        $summary = $this->summary($warehouseId, $catalogItemId, $from, $to);
        $filename = "kardex_{$warehouse->code}_{$item->code}_{$from->format('Ymd')}_{$to->format('Ymd')}.csv";

        return response()->streamDownload(function () use ($summary, $from) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM
            fwrite($handle, "\xEF\xBB\xBF");

            // Cabeceras
            fputcsv($handle, [
                'Fecha / Hora',
                'Tipo de Movimiento',
                'Dirección',
                'Motivo',
                'Tipo Doc. Origen',
                'N° Documento',
                'Entrada Cantidad',
                'Entrada Costo Unitario',
                'Entrada Total',
                'Salida Cantidad',
                'Salida Costo Unitario',
                'Salida Total',
                'Saldo Cantidad',
                'Saldo Costo Promedio',
                'Saldo Total',
                'Usuario',
                'Notas',
            ]);

            // Fila de saldo inicial
            fputcsv($handle, [
                $from->startOfDay()->toIso8601String(),
                'SALDO INICIAL',
                '-',
                '-',
                '-',
                '-',
                '',
                '',
                '',
                '',
                '',
                '',
                number_format($summary['opening_balance']['quantity'], 4, '.', ''),
                number_format($summary['opening_balance']['unit_cost'], 6, '.', ''),
                number_format($summary['opening_balance']['total'], 4, '.', ''),
                'SISTEMA',
                'Saldo acumulado antes del período',
            ]);

            // Movimientos
            /** @var KardexMovement $m */
            foreach ($summary['movements'] as $m) {
                $isIn = $m->direction->value === 'in';
                $reason = $m->reason;
                $user = $m->user;

                fputcsv($handle, [
                    $m->moved_at->toIso8601String(),
                    $m->movement_type->value,
                    $m->direction->value,
                    $reason !== null ? ($reason->name ?? $reason->code) : '-',
                    $m->source_type,
                    $m->source_number,
                    $isIn ? number_format($m->quantity, 4, '.', '') : '',
                    $isIn ? number_format($m->unit_cost, 6, '.', '') : '',
                    $isIn ? number_format($m->total_cost, 4, '.', '') : '',
                    ! $isIn ? number_format($m->quantity, 4, '.', '') : '',
                    ! $isIn ? number_format($m->unit_cost, 6, '.', '') : '',
                    ! $isIn ? number_format($m->total_cost, 4, '.', '') : '',
                    number_format($m->balance_quantity, 4, '.', ''),
                    number_format($m->balance_unit_cost, 6, '.', ''),
                    number_format($m->balance_total_cost, 4, '.', ''),
                    $user !== null ? $user->name : 'Sistema',
                    $m->notes ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
