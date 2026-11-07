<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Enums\WarehouseReasonType;
use App\Modules\Inventory\Exceptions\InventoryRuleException;
use App\Modules\Inventory\Models\WarehouseReason;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class WarehouseReasonService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<WarehouseReason>
     */
    public function query(array $filters = []): Builder
    {
        $query = WarehouseReason::query();

        if (! empty($filters['search'])) {
            $query->search((string) $filters['search']);
        }

        if (! empty($filters['type'])) {
            $type = $filters['type'] instanceof WarehouseReasonType
                ? $filters['type']->value
                : (string) $filters['type'];
            $query->where('type', $type);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (empty($filters['include_system'])) {
            // In list, we show system reasons unless explicitly excluded, but options exclude system by default
        }

        return $query->orderBy('name');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, WarehouseReason>
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = (int) ($filters['page'] ?? 1);

        return $this->query($filters)->paginate(perPage: $perPage, page: $page);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, WarehouseReason>
     */
    public function options(array $filters = []): Collection
    {
        $query = WarehouseReason::query()->active()->orderBy('name');

        if (! empty($filters['type'])) {
            $type = $filters['type'] instanceof WarehouseReasonType
                ? $filters['type']->value
                : (string) $filters['type'];
            $query->where('type', $type);
        }

        if (empty($filters['include_system'])) {
            $query->where('is_system', false);
        }

        return $query->get(['id', 'code', 'name', 'type', 'requires_approval', 'is_system']);
    }

    public function findById(string $id): ?WarehouseReason
    {
        return WarehouseReason::query()->find($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): WarehouseReason
    {
        return WarehouseReason::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(WarehouseReason $reason, array $attributes): WarehouseReason
    {
        if ($reason->is_system) {
            if (array_key_exists('code', $attributes) && $attributes['code'] !== $reason->code) {
                throw new InventoryRuleException('No se puede modificar el código de un motivo de sistema.', 'code');
            }

            if (array_key_exists('type', $attributes)) {
                $newType = $attributes['type'] instanceof WarehouseReasonType
                    ? $attributes['type']->value
                    : (string) $attributes['type'];

                if ($newType !== $reason->type->value) {
                    throw new InventoryRuleException('No se puede modificar el tipo de un motivo de sistema.', 'type');
                }
            }

            if (array_key_exists('is_system', $attributes) && ! $attributes['is_system']) {
                throw new InventoryRuleException('No se puede modificar la condición de sistema de un motivo.', 'is_system');
            }
        }

        $reason->update($attributes);

        return $reason->fresh() ?? $reason;
    }

    public function delete(WarehouseReason $reason): bool
    {
        if ($reason->is_system) {
            throw new InventoryRuleException('No se puede eliminar un motivo de sistema.', 'warehouse_reason');
        }

        return (bool) $reason->delete();
    }
}
