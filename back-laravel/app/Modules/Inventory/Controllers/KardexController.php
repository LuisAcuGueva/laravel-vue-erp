<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Requests\KardexRequest;
use App\Modules\Inventory\Resources\KardexMovementResource;
use App\Modules\Inventory\Services\KardexQueryService;
use App\Modules\Users\Models\User;
use App\Support\Branch\BranchAccessGuard;
use App\Support\Http\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KardexController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected KardexQueryService $kardexQueryService,
        protected BranchAccessGuard $branchAccessGuard
    ) {}

    public function index(KardexRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $warehouseId = (string) $request->input('warehouse_id');
        $catalogItemId = (string) $request->input('catalog_item_id');

        /** @var Warehouse $warehouse */
        $warehouse = Warehouse::findOrFail($warehouseId);
        $this->branchAccessGuard->assertAccessible($user, $warehouse->branch_id);

        $from = Carbon::parse((string) $request->input('date_from'));
        $to = Carbon::parse((string) $request->input('date_to'));

        $summary = $this->kardexQueryService->summary($warehouseId, $catalogItemId, $from, $to);

        return $this->successResponse([
            'opening_balance' => $summary['opening_balance'],
            'movements' => KardexMovementResource::collection($summary['movements']),
            'closing_balance' => $summary['closing_balance'],
        ]);
    }

    public function export(KardexRequest $request): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        $warehouseId = (string) $request->input('warehouse_id');
        $catalogItemId = (string) $request->input('catalog_item_id');

        /** @var Warehouse $warehouse */
        $warehouse = Warehouse::findOrFail($warehouseId);
        $this->branchAccessGuard->assertAccessible($user, $warehouse->branch_id);

        $from = Carbon::parse((string) $request->input('date_from'));
        $to = Carbon::parse((string) $request->input('date_to'));

        return $this->kardexQueryService->streamCsv($warehouseId, $catalogItemId, $from, $to);
    }
}
