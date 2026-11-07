<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Requests\ListStockRequest;
use App\Modules\Inventory\Requests\UpdateStockLimitsRequest;
use App\Modules\Inventory\Resources\StockResource;
use App\Modules\Inventory\Services\StockQueryService;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Users\Models\User;
use App\Support\Branch\ActiveBranch;
use App\Support\Branch\BranchAccessGuard;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected StockQueryService $stockQueryService,
        protected BranchAccessGuard $branchAccessGuard
    ) {}

    public function index(ListStockRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $accessibleBranchIds = $this->branchAccessGuard->accessibleBranchIds($user);

        if ($request->filled('warehouse_id')) {
            /** @var Warehouse $warehouse */
            $warehouse = Warehouse::findOrFail((string) $request->input('warehouse_id'));
            if (! in_array($warehouse->branch_id, $accessibleBranchIds, true)) {
                abort(403, 'Almacén no accesible.');
            }
            $targetWarehouseIds = [$warehouse->id];
        } else {
            /** @var Branch|null $activeBranch */
            $activeBranch = app(ActiveBranch::class)->get();
            if ($activeBranch !== null && in_array($activeBranch->id, $accessibleBranchIds, true)) {
                /** @var list<string> $targetWarehouseIds */
                $targetWarehouseIds = Warehouse::where('branch_id', $activeBranch->id)->pluck('id')->all();
            } else {
                /** @var list<string> $targetWarehouseIds */
                $targetWarehouseIds = Warehouse::whereIn('branch_id', $accessibleBranchIds)->pluck('id')->all();
            }
        }

        $result = $this->stockQueryService->list($request->validated(), $targetWarehouseIds);

        return $this->successResponse(
            data: StockResource::collection($result['paginator']->items()),
            meta: [
                'current_page' => $result['paginator']->currentPage(),
                'per_page' => $result['paginator']->perPage(),
                'total' => $result['paginator']->total(),
                'last_page' => $result['paginator']->lastPage(),
                'total_value' => $result['total_value'],
            ]
        );
    }

    public function byItem(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $accessibleBranchIds = $this->branchAccessGuard->accessibleBranchIds($user);

        /** @var list<string> $accessibleWarehouseIds */
        $accessibleWarehouseIds = Warehouse::whereIn('branch_id', $accessibleBranchIds)->pluck('id')->all();

        $data = $this->stockQueryService->byItem($id, $accessibleWarehouseIds);

        return $this->successResponse(data: $data);
    }

    public function updateLimits(UpdateStockLimitsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $warehouseId = (string) $request->input('warehouse_id');

        /** @var Warehouse $warehouse */
        $warehouse = Warehouse::findOrFail($warehouseId);
        $this->branchAccessGuard->assertAccessible($user, $warehouse->branch_id);

        $stock = $this->stockQueryService->upsertLimits(
            $warehouseId,
            (string) $request->input('catalog_item_id'),
            (float) $request->input('min_stock'),
            $request->input('max_stock') !== null ? (float) $request->input('max_stock') : null,
            $user
        );

        return $this->successResponse(data: new StockResource($stock));
    }
}
