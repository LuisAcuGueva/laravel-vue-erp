<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Requests\ItemPresentationRequest;
use App\Modules\Inventory\Resources\ItemPresentationResource;
use App\Modules\Inventory\Services\CatalogItemService;
use App\Modules\Inventory\Services\ItemPresentationService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class ItemPresentationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CatalogItemService $catalogItemService,
        private readonly ItemPresentationService $presentationService,
    ) {}

    public function index(string $id): JsonResponse
    {
        $item = $this->catalogItemService->find($id);
        $presentations = $this->presentationService->listByItem($item);

        return $this->successResponse(
            data: ItemPresentationResource::collection($presentations),
        );
    }

    public function store(string $id, ItemPresentationRequest $request): JsonResponse
    {
        $item = $this->catalogItemService->find($id);
        $presentation = $this->presentationService->create($item, $request->toAttributes());

        return (new ItemPresentationResource($presentation))
            ->response()
            ->setStatusCode(201);
    }

    public function update(string $id, string $presentationId, ItemPresentationRequest $request): JsonResponse|ItemPresentationResource
    {
        /** @var ItemPresentation $presentation */
        $presentation = ItemPresentation::where('catalog_item_id', $id)->findOrFail($presentationId);
        $updated = $this->presentationService->update($presentation, $request->toAttributes());

        return new ItemPresentationResource($updated);
    }

    public function destroy(string $id, string $presentationId): JsonResponse
    {
        /** @var ItemPresentation $presentation */
        $presentation = ItemPresentation::where('catalog_item_id', $id)->findOrFail($presentationId);
        $this->presentationService->delete($presentation);

        return $this->noContentResponse();
    }
}
