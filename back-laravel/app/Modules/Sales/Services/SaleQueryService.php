<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Sales\Models\Sale;
use App\Modules\Users\Models\User;
use App\Support\Pricing\Decimal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class SaleQueryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Sale>
     */
    public function query(array $filters, User $user, string $branchId): Builder
    {
        $query = Sale::query()
            ->with(['series', 'client', 'seller', 'creator'])
            ->where('branch_id', $branchId);

        if (! $user->can('sales.view_all')) {
            $employeeId = $user->employee?->id;
            $query->where(function (Builder $q) use ($user, $employeeId) {
                $q->where('created_by', $user->id);
                if ($employeeId !== null) {
                    $q->orWhere('seller_id', $employeeId);
                }
            });
        }

        if (! empty($filters['document_type'])) {
            $query->where('document_type', $filters['document_type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (! empty($filters['seller_id'])) {
            $query->where('seller_id', $filters['seller_id']);
        }

        if (! empty($filters['cash_session_id'])) {
            $query->where('cash_session_id', $filters['cash_session_id']);
        }

        if (! empty($filters['payment_condition'])) {
            $query->where('payment_condition', $filters['payment_condition']);
        }

        if (! empty($filters['einvoice_status'])) {
            $query->where('einvoice_status', $filters['einvoice_status']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('issue_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('issue_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%';
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('full_number', 'like', $term)
                    ->orWhere('client_name', 'like', $term)
                    ->orWhere('client_document_number', 'like', $term);
            });
        }

        return $query->orderBy('issue_date', 'desc')->orderBy('created_at', 'desc');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Sale>
     */
    public function paginate(array $filters, User $user, string $branchId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($filters, $user, $branchId)->paginate($perPage);
    }

    /**
     * Calcula los totales consolidados de la consulta de ventas.
     *
     * @param  array<string, mixed>  $filters
     * @return array{total: string, tax_total: string, cost_total: ?string, margin_total: ?string}
     */
    public function totals(array $filters, User $user, string $branchId): array
    {
        // Excluimos anuladas para los totales financieros
        $totalsQuery = (clone $this->query($filters, $user, $branchId))->where('status', 'issued');

        $total = (float) $totalsQuery->sum('total');
        $taxTotal = (float) $totalsQuery->sum('tax_total');

        $costTotal = null;
        $marginTotal = null;

        if ($user->can('sales.view_costs')) {
            $cost = (float) $totalsQuery->sum('cost_total');
            $costTotal = number_format($cost, 2, '.', '');
            $marginTotal = Decimal::sub(number_format($total, 2, '.', ''), $costTotal, 2);
        }

        return [
            'total' => number_format($total, 2, '.', ''),
            'tax_total' => number_format($taxTotal, 2, '.', ''),
            'cost_total' => $costTotal,
            'margin_total' => $marginTotal,
        ];
    }
}
