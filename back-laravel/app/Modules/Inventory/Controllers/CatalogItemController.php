<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Requests\CatalogItemRequest;
use App\Modules\Inventory\Requests\CatalogLookupRequest;
use App\Modules\Inventory\Requests\ListCatalogItemsRequest;
use App\Modules\Inventory\Resources\CatalogItemResource;
use App\Modules\Inventory\Resources\CatalogLookupResource;
use App\Modules\Inventory\Services\CatalogItemService;
use App\Modules\Inventory\Services\CatalogLookupService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class CatalogItemController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CatalogItemService $catalogItemService,
        private readonly CatalogLookupService $catalogLookupService,
    ) {}

    public function index(ListCatalogItemsRequest $request): JsonResponse
    {
        $paginator = $this->catalogItemService->list($request->validated());

        return $this->successResponse(
            data: CatalogItemResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function lookup(CatalogLookupRequest $request): JsonResponse
    {
        $results = $this->catalogLookupService->search(
            q: (string) $request->input('q'),
            context: (string) $request->input('context'),
            limit: (int) ($request->input('limit', 20)),
            warehouseId: $request->input('warehouse_id') ? (string) $request->input('warehouse_id') : null,
        );

        return $this->successResponse(
            data: CatalogLookupResource::collection($results),
        );
    }

    public function store(CatalogItemRequest $request): JsonResponse
    {
        $item = $this->catalogItemService->create(
            attributes: $request->toAttributes(),
            prices: $request->toPrices(),
        );

        return (new CatalogItemResource($item))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): JsonResponse|CatalogItemResource
    {
        $item = $this->catalogItemService->find($id);

        return new CatalogItemResource($item);
    }

    public function update(string $id, CatalogItemRequest $request): JsonResponse|CatalogItemResource
    {
        $item = $this->catalogItemService->find($id);
        $updated = $this->catalogItemService->update($item, $request->toAttributes());

        return new CatalogItemResource($updated);
    }

    public function destroy(string $id): JsonResponse
    {
        $item = $this->catalogItemService->find($id);
        $this->catalogItemService->delete($item);

        return $this->noContentResponse();
    }
}
