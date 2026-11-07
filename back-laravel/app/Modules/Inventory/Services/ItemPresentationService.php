<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Exceptions\InventoryRuleException;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Models\KardexMovement;
use App\Modules\Inventory\Models\Unit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ItemPresentationService
{
    /**
     * @return Collection<int, ItemPresentation>
     */
    public function listByItem(CatalogItem $item): Collection
    {
        return $item->presentations()
            ->with(['unit', 'prices'])
            ->orderByDesc('is_default')
            ->orderBy('factor')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(CatalogItem $item, array $attributes): ItemPresentation
    {
        return DB::transaction(function () use ($item, $attributes): ItemPresentation {
            $baseUnit = $item->baseUnit;
            /** @var Unit $unit */
            $unit = Unit::with('category')->findOrFail($attributes['unit_id']);

            if ($unit->unit_category_id !== $baseUnit->unit_category_id && ! $unit->category->is_packaging) {
                throw new InventoryRuleException(
                    'La unidad debe pertenecer a la misma categoría que la unidad base o a una categoría de empaque.',
                    'unit_id'
                );
            }

            $factor = (float) $attributes['factor'];
            if (! $baseUnit->allow_decimals && floor($factor) != $factor) {
                throw new InventoryRuleException(
                    'La unidad base no permite decimales; el factor debe ser un número entero.',
                    'factor'
                );
            }

            if (! empty($attributes['is_default'])) {
                ItemPresentation::where('catalog_item_id', $item->id)
                    ->update(['is_default' => false]);
            }

            $attributes['catalog_item_id'] = $item->id;
            $attributes['is_base'] = false;

            /** @var ItemPresentation $presentation */
            $presentation = ItemPresentation::create($attributes);

            return $presentation->load(['unit', 'prices']);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(ItemPresentation $presentation, array $attributes): ItemPresentation
    {
        return DB::transaction(function () use ($presentation, $attributes): ItemPresentation {
            $item = $presentation->item;
            $baseUnit = $item->baseUnit;

            if (array_key_exists('unit_id', $attributes) && $attributes['unit_id'] !== $presentation->unit_id) {
                /** @var Unit $unit */
                $unit = Unit::with('category')->findOrFail($attributes['unit_id']);

                if ($unit->unit_category_id !== $baseUnit->unit_category_id && ! $unit->category->is_packaging) {
                    throw new InventoryRuleException(
                        'La unidad debe pertenecer a la misma categoría que la unidad base o a una categoría de empaque.',
                        'unit_id'
                    );
                }
            }

            if (array_key_exists('factor', $attributes)) {
                $factor = (float) $attributes['factor'];
                if ($factor !== (float) $presentation->factor && KardexMovement::where('item_presentation_id', $presentation->id)->exists()) {
                    throw new InventoryRuleException(
                        'No se puede modificar el factor de una presentación con movimientos registrados.',
                        'factor'
                    );
                }

                if (! $baseUnit->allow_decimals && floor($factor) != $factor) {
                    throw new InventoryRuleException(
                        'La unidad base no permite decimales; el factor debe ser un número entero.',
                        'factor'
                    );
                }
            }

            if (array_key_exists('is_default', $attributes)) {
                if ($attributes['is_default']) {
                    ItemPresentation::where('catalog_item_id', $item->id)
                        ->where('id', '!=', $presentation->id)
                        ->update(['is_default' => false]);
                } elseif ($presentation->is_default) {
                    throw new InventoryRuleException(
                        'El ítem debe tener al menos una presentación por defecto.',
                        'is_default'
                    );
                }
            }

            $presentation->update($attributes);

            return $presentation->fresh(['unit', 'prices']) ?? $presentation;
        });
    }

    public function delete(ItemPresentation $presentation): bool
    {
        if ($presentation->is_base) {
            throw new InventoryRuleException('No se puede eliminar la presentación base del ítem.', 'presentation');
        }

        if ($presentation->is_default) {
            throw new InventoryRuleException('No se puede eliminar la presentación por defecto. Asigne otra antes de eliminar.', 'presentation');
        }

        if (KardexMovement::where('item_presentation_id', $presentation->id)->exists()) {
            throw new InventoryRuleException('No se puede eliminar una presentación con movimientos registrados.', 'presentation');
        }

        return (bool) $presentation->delete();
    }
}
