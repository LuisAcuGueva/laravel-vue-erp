<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Exceptions\InventoryRuleException;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\ItemPrice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ItemPriceService
{
    /**
     * Sincroniza en lote los precios de las presentaciones de un ítem.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return Collection<int, ItemPrice>
     */
    public function sync(CatalogItem $item, array $rows): Collection
    {
        return DB::transaction(function () use ($item, $rows): Collection {
            $validPresentationIds = $item->presentations()->pluck('id')->all();

            $updatedIds = [];

            foreach ($rows as $row) {
                $presentationId = (string) $row['presentation_id'];

                if (! in_array($presentationId, $validPresentationIds, true)) {
                    throw new InventoryRuleException('La presentación indicada no pertenece al ítem.', 'prices');
                }

                $priceType = $row['price_type'] instanceof \BackedEnum
                    ? $row['price_type']->value
                    : (string) $row['price_type'];

                /** @var ItemPrice $priceRecord */
                $priceRecord = ItemPrice::updateOrCreate(
                    [
                        'item_presentation_id' => $presentationId,
                        'price_type' => $priceType,
                    ],
                    [
                        'price' => $row['price'],
                        'min_quantity' => $row['min_quantity'] ?? 1,
                    ]
                );

                $updatedIds[] = $priceRecord->id;
            }

            ItemPrice::whereIn('item_presentation_id', $validPresentationIds)
                ->whereNotIn('id', $updatedIds)
                ->delete();

            /** @var Collection<int, ItemPrice> */
            return ItemPrice::whereIn('item_presentation_id', $validPresentationIds)->get();
        });
    }
}
