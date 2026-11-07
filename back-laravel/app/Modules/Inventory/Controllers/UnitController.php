<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Exports\UnitCsvExport;
use App\Modules\Inventory\Requests\ConvertUnitRequest;
use App\Modules\Inventory\Requests\ListUnitsRequest;
use App\Modules\Inventory\Requests\UnitRequest;
use App\Modules\Inventory\Resources\UnitResource;
use App\Modules\Inventory\Services\UnitService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UnitController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly UnitService $unitService,
    ) {}

    public function options(Request $request): JsonResponse
    {
        $options = $this->unitService->options($request->only('unit_category_id'));

        return $this->successResponse(
            data: $options,
        );
    }

    public function export(ListUnitsRequest $request): StreamedResponse
    {
        return app(UnitCsvExport::class)->download(
            $this->unitService->query($request->validated()),
            'unidades',
        );
    }

    public function index(ListUnitsRequest $request): JsonResponse
    {
        $paginator = $this->unitService->list($request->validated());

        return $this->successResponse(
            data: UnitResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(UnitRequest $request): JsonResponse
    {
        $unit = $this->unitService->create($request->toAttributes());

        return (new UnitResource($unit))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): JsonResponse|UnitResource
    {
        $unit = $this->unitService->findById($id);

        if ($unit === null) {
            return $this->errorResponse("Unidad no encontrada: {$id}", 404);
        }

        return new UnitResource($unit);
    }

    public function update(string $id, UnitRequest $request): JsonResponse|UnitResource
    {
        $unit = $this->unitService->findById($id);

        if ($unit === null) {
            return $this->errorResponse("Unidad no encontrada: {$id}", 404);
        }

        $updated = $this->unitService->update($unit, $request->toAttributes());

        return new UnitResource($updated);
    }

    public function destroy(string $id): JsonResponse
    {
        $unit = $this->unitService->findById($id);

        if ($unit === null) {
            return $this->errorResponse("Unidad no encontrada: {$id}", 404);
        }

        $this->unitService->delete($unit);

        return $this->noContentResponse();
    }

    public function convert(ConvertUnitRequest $request): JsonResponse
    {
        $from = $this->unitService->findById((string) $request->input('from_unit_id'));
        $to = $this->unitService->findById((string) $request->input('to_unit_id'));

        if ($from === null || $to === null) {
            return $this->errorResponse('Unidad de origen o destino no encontrada.', 404);
        }

        $quantity = (float) $request->input('quantity');
        $converted = $this->unitService->convert($quantity, $from, $to);

        return $this->successResponse(
            data: [
                'quantity' => $converted,
            ],
        );
    }
}
