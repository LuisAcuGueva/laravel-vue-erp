<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Exports\BrandCsvExport;
use App\Modules\Inventory\Requests\BrandRequest;
use App\Modules\Inventory\Requests\ListBrandsRequest;
use App\Modules\Inventory\Resources\BrandResource;
use App\Modules\Inventory\Services\BrandService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BrandController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly BrandService $brandService,
    ) {}

    public function options(): JsonResponse
    {
        $options = $this->brandService->options();

        return $this->successResponse(
            data: $options,
        );
    }

    public function export(ListBrandsRequest $request): StreamedResponse
    {
        return app(BrandCsvExport::class)->download(
            $this->brandService->query($request->validated()),
            'marcas',
        );
    }

    public function index(ListBrandsRequest $request): JsonResponse
    {
        $paginator = $this->brandService->list($request->validated());

        return $this->successResponse(
            data: BrandResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(BrandRequest $request): JsonResponse
    {
        $brand = $this->brandService->create($request->toAttributes());

        return (new BrandResource($brand))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): JsonResponse|BrandResource
    {
        $brand = $this->brandService->findById($id);

        if ($brand === null) {
            return $this->errorResponse("Marca no encontrada: {$id}", 404);
        }

        return new BrandResource($brand);
    }

    public function update(string $id, BrandRequest $request): JsonResponse|BrandResource
    {
        $brand = $this->brandService->findById($id);

        if ($brand === null) {
            return $this->errorResponse("Marca no encontrada: {$id}", 404);
        }

        $updated = $this->brandService->update($brand, $request->toAttributes());

        return new BrandResource($updated);
    }

    public function destroy(string $id): JsonResponse
    {
        $brand = $this->brandService->findById($id);

        if ($brand === null) {
            return $this->errorResponse("Marca no encontrada: {$id}", 404);
        }

        $this->brandService->delete($brand);

        return $this->noContentResponse();
    }
}
