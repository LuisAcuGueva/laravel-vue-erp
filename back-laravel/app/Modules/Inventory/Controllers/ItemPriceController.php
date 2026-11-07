<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Requests\SyncItemPricesRequest;
use App\Modules\Inventory\Resources\ItemPriceResource;
use App\Modules\Inventory\Services\CatalogItemService;
use App\Modules\Inventory\Services\ItemPriceService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class ItemPriceController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CatalogItemService $catalogItemService,
        private readonly ItemPriceService $priceService,
    ) {}

    public function sync(string $id, SyncItemPricesRequest $request): JsonResponse
    {
        $item = $this->catalogItemService->find($id);
        $prices = $this->priceService->sync($item, $request->toPrices());

        return $this->successResponse(
            data: ItemPriceResource::collection($prices),
            message: 'Precios actualizados correctamente.',
        );
    }
}
