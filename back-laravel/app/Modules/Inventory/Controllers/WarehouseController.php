<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Exports\WarehouseCsvExport;
use App\Modules\Inventory\Requests\ListWarehousesRequest;
use App\Modules\Inventory\Requests\WarehouseRequest;
use App\Modules\Inventory\Resources\WarehouseResource;
use App\Modules\Inventory\Services\WarehouseService;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Maintenance\Services\UserBranchService;
use App\Modules\Users\Models\User;
use App\Support\Branch\ActiveBranch;
use App\Support\Branch\BranchAccessGuard;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WarehouseController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly WarehouseService $warehouseService,
        private readonly BranchAccessGuard $branchAccessGuard,
    ) {}

    public function options(Request $request): JsonResponse
    {
        /** @var Branch|null $branch */
        $branch = app(ActiveBranch::class)->get();
        /** @var User $user */
        $user = $request->user();
        $accessibleBranches = app(UserBranchService::class)->accessibleBranches($user);
        $scope = (string) $request->query('scope', 'active_branch');

        $options = $this->warehouseService->options($branch, $accessibleBranches, $scope);

        return $this->successResponse(
            data: $options,
        );
    }

    public function index(ListWarehousesRequest $request): JsonResponse
    {
        $paginator = $this->warehouseService->list($this->scopedFilters($request));

        return $this->successResponse(
            data: WarehouseResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(WarehouseRequest $request): JsonResponse
    {
        $warehouse = $this->warehouseService->create($request->toAttributes());

        return (new WarehouseResource($warehouse))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id, Request $request): JsonResponse|WarehouseResource
    {
        $warehouse = $this->warehouseService->findById($id);

        if ($warehouse === null) {
            return $this->errorResponse("Almacén no encontrado: {$id}", 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $warehouse->branch_id);

        return new WarehouseResource($warehouse);
    }

    public function update(string $id, WarehouseRequest $request): JsonResponse|WarehouseResource
    {
        $warehouse = $this->warehouseService->findById($id);

        if ($warehouse === null) {
            return $this->errorResponse("Almacén no encontrado: {$id}", 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $warehouse->branch_id);

        $updated = $this->warehouseService->update($warehouse, $request->toAttributes());

        return new WarehouseResource($updated);
    }

    public function destroy(string $id, Request $request): JsonResponse
    {
        $warehouse = $this->warehouseService->findById($id);

        if ($warehouse === null) {
            return $this->errorResponse("Almacén no encontrado: {$id}", 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $warehouse->branch_id);

        $this->warehouseService->delete($warehouse);

        return $this->noContentResponse();
    }

    public function export(ListWarehousesRequest $request): StreamedResponse
    {
        return app(WarehouseCsvExport::class)->download(
            $this->warehouseService->query($this->scopedFilters($request)),
            'almacenes',
        );
    }

    /**
     * Filtros del listado limitados a las sedes accesibles; un `branch_id` explícito se valida (404 si no es accesible).
     *
     * @return array<string, mixed>
     */
    private function scopedFilters(ListWarehousesRequest $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $filters = $request->validated();

        if (! empty($filters['branch_id'])) {
            $this->branchAccessGuard->assertAccessible($user, (string) $filters['branch_id']);
        }

        $filters['branch_ids'] = $this->branchAccessGuard->accessibleBranchIds($user);

        return $filters;
    }
}
