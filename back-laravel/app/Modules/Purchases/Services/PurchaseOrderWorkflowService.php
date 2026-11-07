<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Services;

use App\Modules\Configuration\Services\SystemParameterService;
use App\Modules\Purchases\Enums\PurchaseOrderStatus;
use App\Modules\Purchases\Exceptions\PurchasesRuleException;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseOrderItem;
use App\Modules\Users\Models\User;
use App\Support\Pricing\Decimal;
use Illuminate\Support\Facades\DB;

class PurchaseOrderWorkflowService
{
    public function __construct(
        private readonly SystemParameterService $parameters,
        private readonly PurchaseRequestService $requestService,
        private readonly PurchaseOrderService $orderService,
    ) {}

    /**
     * Envía la OC: pasa a `approved` si su total en moneda base no supera el umbral; si no, a `pending_approval`.
     *
     * @throws PurchasesRuleException
     */
    public function submit(PurchaseOrder $order, User $user): PurchaseOrder
    {
        return $this->apply($order, function (PurchaseOrder $locked): void {
            $threshold = number_format($this->parameters->decimal('purchases.order_approval_threshold', 5000.0), 2, '.', '');
            $within = Decimal::cmp((string) $locked->total_base_currency, $threshold, 2) <= 0;

            $this->transition($locked, $within ? PurchaseOrderStatus::Approved : PurchaseOrderStatus::PendingApproval, $within ? ['approved_at' => now()] : []);
        });
    }

    /**
     * @throws PurchasesRuleException
     */
    public function approve(PurchaseOrder $order, User $user): PurchaseOrder
    {
        return $this->apply($order, function (PurchaseOrder $locked) use ($user): void {
            if ($locked->created_by === $user->id) {
                throw new PurchasesRuleException('El aprobador no puede ser quien creó la orden', 'status');
            }

            $this->transition($locked, PurchaseOrderStatus::Approved, [
                'approved_by' => $user->id,
                'approved_at' => now(),
            ], [PurchaseOrderStatus::PendingApproval]);
        });
    }

    /**
     * Rechaza la OC y libera las cantidades de los requerimientos vinculados.
     *
     * @throws PurchasesRuleException
     */
    public function reject(PurchaseOrder $order, string $reason, User $user): PurchaseOrder
    {
        return $this->apply($order, function (PurchaseOrder $locked) use ($reason, $user): void {
            $this->transition($locked, PurchaseOrderStatus::Rejected, [
                'rejected_by' => $user->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ], [PurchaseOrderStatus::PendingApproval]);

            $this->releaseRequests($locked);
        });
    }

    /**
     * Cierra una OC aprobada o parcialmente recibida (no se recibirá más).
     *
     * @throws PurchasesRuleException
     */
    public function close(PurchaseOrder $order, ?string $reason, User $user): PurchaseOrder
    {
        return $this->apply($order, function (PurchaseOrder $locked) use ($reason, $user): void {
            $this->transition($locked, PurchaseOrderStatus::Closed, [
                'closed_by' => $user->id,
                'closed_at' => now(),
            ], [PurchaseOrderStatus::Approved, PurchaseOrderStatus::PartiallyReceived]);

            if ($reason !== null && $reason !== '') {
                activity()->performedOn($locked)->causedBy($user)->withProperties(['reason' => $reason])->log('closed');
            }
        });
    }

    /**
     * Anula la OC (borrador, pendiente o aprobada sin recepciones) y libera los requerimientos.
     *
     * @throws PurchasesRuleException
     */
    public function cancel(PurchaseOrder $order, string $reason, User $user): PurchaseOrder
    {
        return $this->apply($order, function (PurchaseOrder $locked) use ($reason, $user): void {
            $received = (string) PurchaseOrderItem::query()->where('purchase_order_id', $locked->id)->sum('received_quantity');
            if (Decimal::cmp($received, '0', 4) > 0) {
                throw new PurchasesRuleException('No se puede anular una orden con recepciones; ciérrela en su lugar', 'status');
            }

            $this->transition($locked, PurchaseOrderStatus::Cancelled, [
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ], [PurchaseOrderStatus::Draft, PurchaseOrderStatus::PendingApproval, PurchaseOrderStatus::Approved]);

            $this->releaseRequests($locked);
        });
    }

    /**
     * Fija `partially_received` o `received` según las cantidades recibidas (lo invoca la recepción, spec 023).
     */
    public function markReceived(PurchaseOrder $order): PurchaseOrder
    {
        return $this->apply($order, function (PurchaseOrder $locked): void {
            if (! in_array($locked->status, [PurchaseOrderStatus::Approved, PurchaseOrderStatus::PartiallyReceived, PurchaseOrderStatus::Received], true)) {
                return;
            }

            $items = $locked->items()->get();
            $any = $items->contains(fn (PurchaseOrderItem $i): bool => Decimal::cmp((string) $i->received_quantity, '0', 4) > 0);
            $all = $items->every(fn (PurchaseOrderItem $i): bool => Decimal::cmp((string) $i->received_quantity, (string) $i->quantity, 4) >= 0);

            $target = match (true) {
                $all => PurchaseOrderStatus::Received,
                $any => PurchaseOrderStatus::PartiallyReceived,
                default => PurchaseOrderStatus::Approved,
            };

            if ($target !== $locked->status) {
                $locked->update(['status' => $target]);
            }
        });
    }

    /**
     * Bloquea la fila, ejecuta la acción dentro de una transacción y devuelve la OC recargada.
     *
     * @param  callable(PurchaseOrder): void  $action
     */
    private function apply(PurchaseOrder $order, callable $action): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $action): PurchaseOrder {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $action($locked);

            return $this->orderService->findById($locked->id) ?? $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<PurchaseOrderStatus>|null  $from
     *
     * @throws PurchasesRuleException
     */
    private function transition(PurchaseOrder $order, PurchaseOrderStatus $to, array $attributes, ?array $from = null): void
    {
        $allowed = ($from === null || in_array($order->status, $from, true)) && $order->status->canTransitionTo($to);

        if (! $allowed) {
            throw new PurchasesRuleException("No se puede pasar la orden de «{$order->status->label()}» a «{$to->label()}»", 'status');
        }

        $order->update(array_merge($attributes, ['status' => $to]));
    }

    private function releaseRequests(PurchaseOrder $order): void
    {
        $ids = [];
        foreach ($order->items()->pluck('purchase_request_item_id') as $id) {
            if ($id !== null) {
                $ids[] = (string) $id;
            }
        }

        $this->requestService->refreshOrderedQuantities($ids);
    }
}
