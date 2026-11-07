<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\WarehouseNote;
use App\Modules\Inventory\Requests\CancelDocumentRequest;
use App\Modules\Inventory\Requests\ListWarehouseNotesRequest;
use App\Modules\Inventory\Requests\StoreWarehouseNoteRequest;
use App\Modules\Inventory\Requests\UpdateWarehouseNoteRequest;
use App\Modules\Inventory\Resources\WarehouseNoteResource;
use App\Modules\Inventory\Services\WarehouseNoteApprovalService;
use App\Modules\Inventory\Services\WarehouseNoteService;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Users\Models\User;
use App\Support\Branch\ActiveBranch;
use App\Support\Branch\BranchAccessGuard;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseNoteController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected WarehouseNoteService $noteService,
        protected WarehouseNoteApprovalService $approvalService,
        protected BranchAccessGuard $branchAccessGuard
    ) {}

    public function index(ListWarehouseNotesRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $accessibleBranchIds = $this->branchAccessGuard->accessibleBranchIds($user);
        /** @var Branch|null $activeBranch */
        $activeBranch = app(ActiveBranch::class)->get();

        $query = $this->noteService->query($request->validated(), $accessibleBranchIds, $activeBranch);

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $query->paginate($perPage);

        return $this->successResponse(
            data: WarehouseNoteResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreWarehouseNoteRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var Branch|null $activeBranch */
        $activeBranch = app(ActiveBranch::class)->get();

        if ($activeBranch === null) {
            abort(400, 'Sede activa no definida.');
        }

        $this->branchAccessGuard->assertAccessible($user, $activeBranch->id);

        $note = $this->noteService->create($request->toAttributes(), $user, $activeBranch);

        $meta = [];
        if ($request->boolean('approve') && $note->isDraft()) {
            $meta['approval_pending'] = true;
        }

        return $this->createdResponse(
            data: new WarehouseNoteResource($note),
            message: 'Nota de almacén registrada correctamente.',
            meta: $meta
        );
    }

    public function show(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $accessibleBranchIds = $this->branchAccessGuard->accessibleBranchIds($user);

        /** @var WarehouseNote $note */
        $note = WarehouseNote::with([
            'branch',
            'warehouse',
            'targetWarehouse.branch',
            'reason',
            'creator',
            'approver',
            'canceller',
            'items.catalogItem.baseUnit',
            'items.presentation.unit',
            'kardexMovements.reason',
            'kardexMovements.user',
        ])->findOrFail($id);

        $isOriginAccessible = in_array($note->branch_id, $accessibleBranchIds, true);
        $isTargetAccessible = $note->targetWarehouse && in_array($note->targetWarehouse->branch_id, $accessibleBranchIds, true);

        if (! $isOriginAccessible && ! $isTargetAccessible) {
            abort(404, 'Nota de almacén no encontrada.');
        }

        return $this->successResponse(
            data: new WarehouseNoteResource($note)
        );
    }

    public function update(string $id, UpdateWarehouseNoteRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var WarehouseNote $note */
        $note = WarehouseNote::findOrFail($id);
        $this->branchAccessGuard->assertAccessible($user, $note->branch_id);

        $updatedNote = $this->noteService->update($note, $request->toAttributes(), $user);

        return $this->successResponse(
            data: new WarehouseNoteResource($updatedNote),
            message: 'Nota de almacén actualizada correctamente.'
        );
    }

    public function approve(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var WarehouseNote $note */
        $note = WarehouseNote::findOrFail($id);
        $this->branchAccessGuard->assertAccessible($user, $note->branch_id);

        $approvedNote = $this->approvalService->approve($note, $user);

        return $this->successResponse(
            data: new WarehouseNoteResource($approvedNote),
            message: 'Nota de almacén aprobada correctamente.'
        );
    }

    public function cancel(string $id, CancelDocumentRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var WarehouseNote $note */
        $note = WarehouseNote::findOrFail($id);
        $this->branchAccessGuard->assertAccessible($user, $note->branch_id);

        $cancelledNote = $this->approvalService->cancel($note, $request->reason(), $user);

        return $this->successResponse(
            data: new WarehouseNoteResource($cancelledNote),
            message: 'Nota de almacén anulada correctamente.'
        );
    }
}
