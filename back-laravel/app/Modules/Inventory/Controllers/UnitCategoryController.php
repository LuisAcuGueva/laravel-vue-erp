<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Requests\ListUnitCategoriesRequest;
use App\Modules\Inventory\Requests\UnitCategoryRequest;
use App\Modules\Inventory\Resources\UnitCategoryResource;
use App\Modules\Inventory\Services\UnitService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class UnitCategoryController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly UnitService $unitService,
    ) {}

    public function options(): JsonResponse
    {
        $options = $this->unitService->optionsCategories();

        return $this->successResponse(
            data: $options,
        );
    }

    public function index(ListUnitCategoriesRequest $request): JsonResponse
    {
        $paginator = $this->unitService->listCategories($request->validated());

        return $this->successResponse(
            data: UnitCategoryResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(UnitCategoryRequest $request): JsonResponse
    {
        $category = $this->unitService->createCategory($request->toAttributes());

        return (new UnitCategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): JsonResponse|UnitCategoryResource
    {
        $category = $this->unitService->findCategoryById($id);

        if ($category === null) {
            return $this->errorResponse("Categoría de unidad no encontrada: {$id}", 404);
        }

        return new UnitCategoryResource($category);
    }

    public function update(string $id, UnitCategoryRequest $request): JsonResponse|UnitCategoryResource
    {
        $category = $this->unitService->findCategoryById($id);

        if ($category === null) {
            return $this->errorResponse("Categoría de unidad no encontrada: {$id}", 404);
        }

        $updated = $this->unitService->updateCategory($category, $request->toAttributes());

        return new UnitCategoryResource($updated);
    }

    public function destroy(string $id): JsonResponse
    {
        $category = $this->unitService->findCategoryById($id);

        if ($category === null) {
            return $this->errorResponse("Categoría de unidad no encontrada: {$id}", 404);
        }

        $this->unitService->deleteCategory($category);

        return $this->noContentResponse();
    }
}
