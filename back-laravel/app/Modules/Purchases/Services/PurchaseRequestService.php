<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Services;

use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\CategoryService;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Purchases\Enums\PurchaseRequestStatus;
use App\Modules\Purchases\Exceptions\PurchasesRuleException;
use App\Modules\Purchases\Models\PurchaseOrderItem;
use App\Modules\Purchases\Models\PurchaseRequest;
use App\Modules\Purchases\Models\PurchaseRequestItem;
use App\Modules\Users\Models\User;
use App\Support\Numbering\SequentialCodeGenerator;
use App\Support\Pricing\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseRequestService
{
    public function __construct(
        private readonly CategoryService $categoryService,
    ) {}

    /**
     * Listado base (sin paginar) acotado a la sede indicada.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<PurchaseRequest>
     */
    public function query(array $filters, User $user, string $branchId): Builder
    {
        $query = PurchaseRequest::query()
            ->with(['area:id,name', 'warehouse:id,code,name', 'requester:id,name'])
            ->withCount('items')
            ->where('branch_id', $branchId);

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', (string) $filters['priority']);
        }

        if (! empty($filters['area_id'])) {
            $query->where('area_id', (string) $filters['area_id']);
        }

        if (isset($filters['mine']) && filter_var($filters['mine'], FILTER_VALIDATE_BOOLEAN)) {
            $query->where('requested_by', $user->id);
        }

        if (! empty($filters['search'])) {
            $like = '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%';
            $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where('number', $operator, $like);
        }

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    public function findById(string $id): ?PurchaseRequest
    {
        return PurchaseRequest::query()
            ->with([
                'branch:id,code,name',
                'area:id,name',
                'warehouse:id,code,name',
                'requester:id,name',
                'approver:id,name',
                'items.presentation:id,name,unit_id',
                'items.presentation.unit:id,name,symbol',
                'items.catalogItem:id,code,name',
            ])
            ->find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws PurchasesRuleException
     */
    public function create(array $data, User $user, Branch $branch): PurchaseRequest
    {
        $this->assertWarehouseInBranch((string) $data['warehouse_id'], $branch->id);
        $items = $this->normalizeItems($data['items']);

        return DB::transaction(function () use ($data, $user, $branch, $items): PurchaseRequest {
            $request = PurchaseRequest::create([
                'number' => SequentialCodeGenerator::next('purchase_request', 'RQ'),
                'branch_id' => $branch->id,
                'area_id' => $data['area_id'],
                'warehouse_id' => $data['warehouse_id'],
                'requested_by' => $user->id,
                'required_date' => $data['required_date'],
                'priority' => $data['priority'],
                'status' => PurchaseRequestStatus::Draft,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($request, $items);

            return $this->findById($request->id) ?? $request;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws PurchasesRuleException
     */
    public function update(PurchaseRequest $request, array $data, User $user): PurchaseRequest
    {
        $this->assertCreator($request, $user);
        $this->assertWarehouseInBranch((string) $data['warehouse_id'], $request->branch_id);
        $items = $this->normalizeItems($data['items']);

        return DB::transaction(function () use ($request, $data, $items): PurchaseRequest {
            /** @var PurchaseRequest $locked */
            $locked = PurchaseRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isDraft()) {
                throw new PurchasesRuleException('Solo se puede editar un requerimiento en borrador', 'status');
            }

            $locked->update([
                'area_id' => $data['area_id'],
                'warehouse_id' => $data['warehouse_id'],
                'required_date' => $data['required_date'],
                'priority' => $data['priority'],
                'notes' => $data['notes'] ?? null,
            ]);

            $locked->items()->delete();
            $this->syncItems($locked, $items);

            return $this->findById($locked->id) ?? $locked;
        });
    }

    /**
     * @throws PurchasesRuleException
     */
    public function submit(PurchaseRequest $request, User $user): PurchaseRequest
    {
        $this->assertCreator($request, $user);

        return $this->transition($request, PurchaseRequestStatus::Submitted, []);
    }

    /**
     * @throws PurchasesRuleException
     */
    public function approve(PurchaseRequest $request, User $user): PurchaseRequest
    {
        return $this->transition($request, PurchaseRequestStatus::Approved, [
            'approved_by' => $user->id,
            'approved_at' => now(),
        ], from: [PurchaseRequestStatus::Submitted]);
    }

    /**
     * @throws PurchasesRuleException
     */
    public function reject(PurchaseRequest $request, string $reason, User $user): PurchaseRequest
    {
        return $this->transition($request, PurchaseRequestStatus::Rejected, [
            'rejected_by' => $user->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ], from: [PurchaseRequestStatus::Submitted]);
    }

    /**
     * Anula el requerimiento; solo si ninguna OC (no anulada) lo referencia.
     *
     * @throws PurchasesRuleException
     */
    public function cancel(PurchaseRequest $request, string $reason, User $user): PurchaseRequest
    {
        return DB::transaction(function () use ($request, $reason, $user): PurchaseRequest {
            /** @var PurchaseRequest $locked */
            $locked = PurchaseRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            $hasOrders = PurchaseOrderItem::query()
                ->whereIn('purchase_request_item_id', $locked->items()->pluck('id'))
                ->whereHas('order', fn ($q) => $q->whereNotIn('status', ['rejected', 'cancelled']))
                ->exists();

            if ($hasOrders) {
                throw new PurchasesRuleException('No se puede anular un requerimiento que tiene órdenes de compra', 'status');
            }

            return $this->transition($locked, PurchaseRequestStatus::Cancelled, [
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);
        });
    }

    /**
     * Crea un borrador con los ítems bajo el stock mínimo del almacén.
     *
     * @throws PurchasesRuleException
     */
    public function fromLowStock(string $warehouseId, string $areaId, ?string $categoryId, User $user, Branch $branch): PurchaseRequest
    {
        $this->assertWarehouseInBranch($warehouseId, $branch->id);

        $query = Stock::query()
            ->with(['catalogItem.defaultPresentation'])
            ->where('warehouse_id', $warehouseId)
            ->whereRaw('stocks.quantity < stocks.min_stock')
            ->whereHas('catalogItem', function ($q) use ($categoryId) {
                $q->where('is_active', true)->where('is_purchasable', true);

                if ($categoryId !== null) {
                    $q->whereIn('category_id', array_merge([$categoryId], $this->categoryService->descendantIds($categoryId)));
                }
            });

        $items = [];
        foreach ($query->get() as $stock) {
            $presentation = $stock->catalogItem->defaultPresentation;
            if ($presentation === null) {
                continue;
            }

            $target = max((float) ($stock->max_stock ?? 0), (float) $stock->min_stock);
            $suggested = ($target - (float) $stock->quantity) / max((float) $presentation->factor, 0.0001);

            $items[] = [
                'item_presentation_id' => $presentation->id,
                'description' => null,
                'quantity' => number_format(max($suggested, 0.0001), 4, '.', ''),
                'notes' => 'Reposición por stock bajo mínimo',
            ];
        }

        if ($items === []) {
            throw new PurchasesRuleException('No hay ítems por debajo del stock mínimo', 'warehouse_id');
        }

        return $this->create([
            'area_id' => $areaId,
            'warehouse_id' => $warehouseId,
            'required_date' => now()->toDateString(),
            'priority' => 'normal',
            'notes' => 'Generado desde stock bajo mínimo',
            'items' => $items,
        ], $user, $branch);
    }

    /**
     * Líneas de requerimientos aprobados con saldo por ordenar, para armar una OC.
     *
     * @return Collection<int, PurchaseRequestItem>
     */
    public function pendingItems(string $warehouseId): Collection
    {
        return PurchaseRequestItem::query()
            ->with([
                'request:id,number,area_id,warehouse_id,required_date,priority',
                'request.area:id,name',
                'presentation:id,name,unit_id',
                'presentation.unit:id,name,symbol',
                'catalogItem:id,code,name',
            ])
            ->whereRaw('purchase_request_items.quantity > purchase_request_items.ordered_quantity')
            ->whereHas('request', fn ($q) => $q
                ->where('warehouse_id', $warehouseId)
                ->whereIn('status', [PurchaseRequestStatus::Approved->value, PurchaseRequestStatus::PartiallyOrdered->value]))
            ->orderBy('purchase_request_id')
            ->orderBy('line')
            ->get();
    }

    /**
     * Recalcula `ordered_quantity` desde las líneas de OC que siguen reservando cantidad y
     * ajusta el estado de los requerimientos afectados (aprobado / parcialmente ordenado / ordenado).
     *
     * @param  list<string>  $requestItemIds
     */
    public function refreshOrderedQuantities(array $requestItemIds): void
    {
        $requestItemIds = array_values(array_unique(array_filter($requestItemIds)));
        if ($requestItemIds === []) {
            return;
        }

        DB::transaction(function () use ($requestItemIds): void {
            $items = PurchaseRequestItem::query()->whereIn('id', $requestItemIds)->lockForUpdate()->get();

            foreach ($items as $item) {
                $ordered = (string) PurchaseOrderItem::query()
                    ->where('purchase_request_item_id', $item->id)
                    ->whereHas('order', fn ($q) => $q->whereNotIn('status', ['rejected', 'cancelled']))
                    ->sum('quantity');

                $item->update(['ordered_quantity' => Decimal::add($ordered, '0', 4)]);
            }

            foreach ($items->pluck('purchase_request_id')->unique() as $requestId) {
                $this->refreshStatus((string) $requestId);
            }
        });
    }

    private function refreshStatus(string $requestId): void
    {
        /** @var PurchaseRequest|null $request */
        $request = PurchaseRequest::query()->whereKey($requestId)->lockForUpdate()->first();

        if ($request === null || ! in_array($request->status, [PurchaseRequestStatus::Approved, PurchaseRequestStatus::PartiallyOrdered, PurchaseRequestStatus::Ordered], true)) {
            return;
        }

        $items = $request->items()->get();
        $anyOrdered = $items->contains(fn (PurchaseRequestItem $i): bool => Decimal::cmp((string) $i->ordered_quantity, '0', 4) > 0);
        $allOrdered = $items->every(fn (PurchaseRequestItem $i): bool => Decimal::cmp((string) $i->ordered_quantity, (string) $i->quantity, 4) >= 0);

        $status = match (true) {
            ! $anyOrdered => PurchaseRequestStatus::Approved,
            $allOrdered => PurchaseRequestStatus::Ordered,
            default => PurchaseRequestStatus::PartiallyOrdered,
        };

        if ($status !== $request->status) {
            $request->update(['status' => $status]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<PurchaseRequestStatus>|null  $from  Estados de origen permitidos (por defecto, los que admite `canTransitionTo`).
     *
     * @throws PurchasesRuleException
     */
    private function transition(PurchaseRequest $request, PurchaseRequestStatus $to, array $attributes, ?array $from = null): PurchaseRequest
    {
        return DB::transaction(function () use ($request, $to, $attributes, $from): PurchaseRequest {
            /** @var PurchaseRequest $locked */
            $locked = PurchaseRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            $allowed = $from !== null ? in_array($locked->status, $from, true) : $locked->status->canTransitionTo($to);

            if (! $allowed || ! $locked->status->canTransitionTo($to)) {
                throw new PurchasesRuleException("No se puede pasar el requerimiento de «{$locked->status->label()}» a «{$to->label()}»", 'status');
            }

            $locked->update(array_merge($attributes, ['status' => $to]));

            return $this->findById($locked->id) ?? $locked;
        });
    }

    private function assertCreator(PurchaseRequest $request, User $user): void
    {
        if ($request->requested_by !== $user->id) {
            abort(403, 'Solo quien creó el requerimiento puede modificarlo o enviarlo.');
        }
    }

    /**
     * @throws PurchasesRuleException
     */
    private function assertWarehouseInBranch(string $warehouseId, string $branchId): void
    {
        $ok = Warehouse::query()->whereKey($warehouseId)->where('branch_id', $branchId)->exists();

        if (! $ok) {
            throw new PurchasesRuleException('El almacén debe pertenecer a la sede activa', 'warehouse_id');
        }
    }

    /**
     * Resuelve el ítem de catálogo de cada línea con presentación y valida que sea comprable.
     *
     * @return list<array<string, mixed>>
     *
     * @throws PurchasesRuleException
     */
    private function normalizeItems(mixed $items): array
    {
        $normalized = [];

        foreach ((array) $items as $index => $item) {
            $presentationId = $item['item_presentation_id'] ?? null;
            $catalogItemId = null;

            if ($presentationId !== null) {
                $presentation = ItemPresentation::query()->whereKey($presentationId)->first();
                /** @var CatalogItem|null $catalog */
                $catalog = $presentation?->item;

                if ($presentation === null || $catalog === null || ! $catalog->is_active || ! $catalog->is_purchasable) {
                    throw new PurchasesRuleException('El artículo no está disponible para compra', "items.{$index}.item_presentation_id");
                }

                $catalogItemId = $catalog->id;
            }

            $normalized[] = [
                'catalog_item_id' => $catalogItemId,
                'item_presentation_id' => $presentationId,
                'description' => $presentationId === null ? ($item['description'] ?? null) : null,
                'quantity' => (string) $item['quantity'],
                'notes' => $item['notes'] ?? null,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function syncItems(PurchaseRequest $request, array $items): void
    {
        foreach ($items as $index => $item) {
            $request->items()->create(array_merge($item, ['line' => $index + 1]));
        }
    }
}
