<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Purchases\Requests\ProviderPriceRequest;
use App\Modules\Purchases\Resources\ProviderPriceResource;
use App\Modules\Purchases\Services\ProviderPriceService;
use App\Modules\Purchases\Services\ProviderService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProviderPriceController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ProviderService $providerService,
        private readonly ProviderPriceService $priceService,
    ) {}

    public function index(string $id): JsonResponse
    {
        $provider = $this->providerService->findById($id);

        if ($provider === null) {
            return $this->errorResponse("Proveedor no encontrado: {$id}", 404);
        }

        return $this->successResponse(
            data: ProviderPriceResource::collection($this->priceService->listForProvider($provider)),
        );
    }

    public function store(string $id, ProviderPriceRequest $request): JsonResponse
    {
        $provider = $this->providerService->findById($id);

        if ($provider === null) {
            return $this->errorResponse("Proveedor no encontrado: {$id}", 404);
        }

        $price = $this->priceService->create($provider, $request->toAttributes());

        return (new ProviderPriceResource($price))
            ->response()
            ->setStatusCode(201);
    }

    public function update(string $id, ProviderPriceRequest $request): JsonResponse|ProviderPriceResource
    {
        $price = $this->priceService->findById($id);

        if ($price === null) {
            return $this->errorResponse("Precio no encontrado: {$id}", 404);
        }

        return new ProviderPriceResource($this->priceService->update($price, $request->toAttributes()));
    }

    public function destroy(string $id): JsonResponse
    {
        $price = $this->priceService->findById($id);

        if ($price === null) {
            return $this->errorResponse("Precio no encontrado: {$id}", 404);
        }

        $this->priceService->delete($price);

        return $this->noContentResponse();
    }

    public function compare(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_presentation_id' => ['required', 'uuid', Rule::exists('item_presentations', 'id')->whereNull('deleted_at')],
        ]);

        /** @var ItemPresentation $presentation */
        $presentation = ItemPresentation::query()->with('item')->findOrFail($data['item_presentation_id']);
        $result = $this->priceService->compare($presentation);

        return $this->successResponse(data: [
            'prices' => ProviderPriceResource::collection($result['prices']),
            'last_purchase_cost' => $result['last_purchase_cost'],
        ]);
    }
}
