<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\InventoryCount;
use App\Modules\Inventory\Requests\CloseInventoryCountRequest;
use App\Modules\Inventory\Requests\StoreInventoryCountRequest;
use App\Modules\Inventory\Requests\UpdateInventoryCountItemsRequest;
use App\Modules\Inventory\Resources\InventoryCountResource;
use App\Modules\Inventory\Services\InventoryCountService;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Users\Models\User;
use App\Support\Branch\ActiveBranch;
use App\Support\Branch\BranchAccessGuard;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryCountController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected InventoryCountService $countService,
        protected BranchAccessGuard $branchAccessGuard
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $accessibleBranchIds = $this->branchAccessGuard->accessibleBranchIds($user);
        /** @var Branch|null $activeBranch */
        $activeBranch = app(ActiveBranch::class)->get();

        $filters = $request->only(['warehouse_id', 'status', 'category_id', 'date_from', 'date_to', 'search']);
        $query = $this->countService->query($filters, $accessibleBranchIds, $activeBranch);

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $query->paginate($perPage);

        return $this->successResponse(
            data: InventoryCountResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreInventoryCountRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var Branch|null $activeBranch */
        $activeBranch = app(ActiveBranch::class)->get();

        if ($activeBranch === null) {
            abort(400, 'Sede activa no definida.');
        }

        $this->branchAccessGuard->assertAccessible($user, $activeBranch->id);

        $count = $this->countService->create($request->toAttributes(), $user, $activeBranch);

        return $this->createdResponse(
            data: new InventoryCountResource($count),
            message: 'Toma de inventario iniciada correctamente.'
        );
    }

    public function show(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var InventoryCount $count */
        $count = InventoryCount::with([
            'branch',
            'warehouse',
            'category',
            'creator',
            'closer',
            'canceller',
            'items.catalogItem.baseUnit',
            'items.counter',
            'warehouseNotes.items',
            'warehouseNotes.reason',
        ])->findOrFail($id);

        $this->branchAccessGuard->assertAccessible($user, $count->branch_id);

        return $this->successResponse(
            data: new InventoryCountResource($count)
        );
    }

    public function updateItems(string $id, UpdateInventoryCountItemsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var InventoryCount $count */
        $count = InventoryCount::findOrFail($id);
        $this->branchAccessGuard->assertAccessible($user, $count->branch_id);

        $updatedCount = $this->countService->updateItems($count, $request->items(), $user);

        return $this->successResponse(
            data: new InventoryCountResource($updatedCount),
            message: 'Líneas de conteo actualizadas correctamente.'
        );
    }

    public function close(string $id, CloseInventoryCountRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var InventoryCount $count */
        $count = InventoryCount::findOrFail($id);
        $this->branchAccessGuard->assertAccessible($user, $count->branch_id);

        $closedCount = $this->countService->close($count, $request->treatUncountedAsZero(), $user);

        return $this->successResponse(
            data: new InventoryCountResource($closedCount),
            message: 'Toma de inventario cerrada y ajustes generados correctamente.'
        );
    }

    public function cancel(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var InventoryCount $count */
        $count = InventoryCount::findOrFail($id);
        $this->branchAccessGuard->assertAccessible($user, $count->branch_id);

        $cancelledCount = $this->countService->cancel($count, $user);

        return $this->successResponse(
            data: new InventoryCountResource($cancelledCount),
            message: 'Toma de inventario anulada correctamente.'
        );
    }
}
