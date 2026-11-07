<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Exports\CategoryCsvExport;
use App\Modules\Inventory\Requests\CategoryRequest;
use App\Modules\Inventory\Requests\ListCategoriesRequest;
use App\Modules\Inventory\Resources\CategoryResource;
use App\Modules\Inventory\Resources\CategoryTreeResource;
use App\Modules\Inventory\Services\CategoryService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CategoryController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CategoryService $categoryService,
    ) {}

    public function tree(): JsonResponse
    {
        $categories = $this->categoryService->tree();

        return $this->successResponse(
            data: CategoryTreeResource::collection($categories),
        );
    }

    public function options(): JsonResponse
    {
        $options = $this->categoryService->options();

        return $this->successResponse(
            data: $options,
        );
    }

    public function export(ListCategoriesRequest $request): StreamedResponse
    {
        return app(CategoryCsvExport::class)->download(
            $this->categoryService->query($request->validated()),
            'categorias',
        );
    }

    public function index(ListCategoriesRequest $request): JsonResponse
    {
        $paginator = $this->categoryService->list($request->validated());

        return $this->successResponse(
            data: CategoryResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create($request->toAttributes());

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): JsonResponse|CategoryResource
    {
        $category = $this->categoryService->findById($id);

        if ($category === null) {
            return $this->errorResponse("Categoría no encontrada: {$id}", 404);
        }

        return new CategoryResource($category);
    }

    public function update(string $id, CategoryRequest $request): JsonResponse|CategoryResource
    {
        $category = $this->categoryService->findById($id);

        if ($category === null) {
            return $this->errorResponse("Categoría no encontrada: {$id}", 404);
        }

        $updated = $this->categoryService->update($category, $request->toAttributes());

        return new CategoryResource($updated);
    }

    public function destroy(string $id): JsonResponse
    {
        $category = $this->categoryService->findById($id);

        if ($category === null) {
            return $this->errorResponse("Categoría no encontrada: {$id}", 404);
        }

        $this->categoryService->delete($category);

        return $this->noContentResponse();
    }
}
