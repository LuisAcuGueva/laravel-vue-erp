<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Exports\WarehouseReasonCsvExport;
use App\Modules\Inventory\Requests\ListWarehouseReasonsRequest;
use App\Modules\Inventory\Requests\WarehouseReasonRequest;
use App\Modules\Inventory\Resources\WarehouseReasonResource;
use App\Modules\Inventory\Services\WarehouseReasonService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WarehouseReasonController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly WarehouseReasonService $warehouseReasonService,
    ) {}

    public function options(Request $request): JsonResponse
    {
        $options = $this->warehouseReasonService->options($request->all());

        return $this->successResponse(
            data: WarehouseReasonResource::collection($options),
        );
    }

    public function export(ListWarehouseReasonsRequest $request): StreamedResponse
    {
        return app(WarehouseReasonCsvExport::class)->download(
            $this->warehouseReasonService->query($request->validated()),
            'motivos_almacen',
        );
    }

    public function index(ListWarehouseReasonsRequest $request): JsonResponse
    {
        $paginator = $this->warehouseReasonService->list($request->validated());

        return $this->successResponse(
            data: WarehouseReasonResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(WarehouseReasonRequest $request): JsonResponse
    {
        $reason = $this->warehouseReasonService->create($request->toAttributes());

        return (new WarehouseReasonResource($reason))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): JsonResponse|WarehouseReasonResource
    {
        $reason = $this->warehouseReasonService->findById($id);

        if ($reason === null) {
            return $this->errorResponse("Motivo no encontrado: {$id}", 404);
        }

        return new WarehouseReasonResource($reason);
    }

    public function update(string $id, WarehouseReasonRequest $request): JsonResponse|WarehouseReasonResource
    {
        $reason = $this->warehouseReasonService->findById($id);

        if ($reason === null) {
            return $this->errorResponse("Motivo no encontrado: {$id}", 404);
        }

        $updated = $this->warehouseReasonService->update($reason, $request->toAttributes());

        return new WarehouseReasonResource($updated);
    }

    public function destroy(string $id): JsonResponse
    {
        $reason = $this->warehouseReasonService->findById($id);

        if ($reason === null) {
            return $this->errorResponse("Motivo no encontrado: {$id}", 404);
        }

        $this->warehouseReasonService->delete($reason);

        return $this->noContentResponse();
    }
}
