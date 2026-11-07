<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Purchases\Requests\ListProvidersRequest;
use App\Modules\Purchases\Requests\ProviderRequest;
use App\Modules\Purchases\Resources\ProviderOptionResource;
use App\Modules\Purchases\Resources\ProviderResource;
use App\Modules\Purchases\Services\ProviderService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ProviderService $providerService,
    ) {}

    public function options(Request $request): JsonResponse
    {
        $search = $request->query('search');

        return $this->successResponse(
            data: ProviderOptionResource::collection(
                $this->providerService->options(is_string($search) && $search !== '' ? $search : null),
            ),
        );
    }

    public function index(ListProvidersRequest $request): JsonResponse
    {
        $paginator = $this->providerService->list($request->validated());

        return $this->successResponse(
            data: ProviderResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(ProviderRequest $request): JsonResponse
    {
        $provider = $this->providerService->create($request->toAttributes());

        return (new ProviderResource($provider))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): JsonResponse|ProviderResource
    {
        $provider = $this->providerService->findById($id);

        if ($provider === null) {
            return $this->errorResponse("Proveedor no encontrado: {$id}", 404);
        }

        return new ProviderResource($provider);
    }

    public function update(string $id, ProviderRequest $request): JsonResponse|ProviderResource
    {
        $provider = $this->providerService->findById($id);

        if ($provider === null) {
            return $this->errorResponse("Proveedor no encontrado: {$id}", 404);
        }

        return new ProviderResource($this->providerService->update($provider, $request->toAttributes()));
    }

    public function destroy(string $id): JsonResponse
    {
        $provider = $this->providerService->findById($id);

        if ($provider === null) {
            return $this->errorResponse("Proveedor no encontrado: {$id}", 404);
        }

        $this->providerService->delete($provider);

        return $this->noContentResponse();
    }
}
