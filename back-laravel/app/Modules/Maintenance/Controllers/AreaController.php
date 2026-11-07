<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Maintenance\Exports\AreaCsvExport;
use App\Modules\Maintenance\Requests\AreaRequest;
use App\Modules\Maintenance\Requests\ListAreasRequest;
use App\Modules\Maintenance\Resources\AreaOptionResource;
use App\Modules\Maintenance\Resources\AreaResource;
use App\Modules\Maintenance\Services\AreaService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AreaController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AreaService $areaService,
    ) {}

    public function options(): JsonResponse
    {
        $areas = $this->areaService->options();

        return $this->successResponse(
            data: AreaOptionResource::collection($areas),
        );
    }

    public function index(ListAreasRequest $request): JsonResponse
    {
        $paginator = $this->areaService->list($request->validated());

        return $this->successResponse(
            data: AreaResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function export(ListAreasRequest $request): StreamedResponse
    {
        return app(AreaCsvExport::class)->download(
            $this->areaService->query($request->validated()),
            'areas',
        );
    }

    public function store(AreaRequest $request): JsonResponse
    {
        $area = $this->areaService->create($request->toAttributes());

        return (new AreaResource($area))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): JsonResponse|AreaResource
    {
        $area = $this->areaService->findById($id);

        if ($area === null) {
            return $this->errorResponse("Área no encontrada: {$id}", 404);
        }

        return new AreaResource($area);
    }

    public function update(string $id, AreaRequest $request): JsonResponse|AreaResource
    {
        $area = $this->areaService->findById($id);

        if ($area === null) {
            return $this->errorResponse("Área no encontrada: {$id}", 404);
        }

        $updated = $this->areaService->update($area, $request->toAttributes());

        return new AreaResource($updated);
    }

    public function destroy(string $id): JsonResponse
    {
        $area = $this->areaService->findById($id);

        if ($area === null) {
            return $this->errorResponse("Área no encontrada: {$id}", 404);
        }

        $this->areaService->delete($area);

        return $this->noContentResponse();
    }
}
