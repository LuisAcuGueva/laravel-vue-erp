<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\CashTransfer;
use App\Modules\Finance\Requests\ListCashTransfersRequest;
use App\Modules\Finance\Requests\RejectCashTransferRequest;
use App\Modules\Finance\Requests\StoreCashTransferRequest;
use App\Modules\Finance\Resources\CashTransferResource;
use App\Modules\Finance\Services\CashTransferService;
use App\Modules\Users\Models\User;
use App\Support\Branch\ActiveBranch;
use App\Support\Branch\BranchAccessGuard;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashTransferController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CashTransferService $transferService,
        private readonly BranchAccessGuard $branchAccessGuard,
    ) {}

    public function index(ListCashTransfersRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $activeBranch = app(ActiveBranch::class)->get();

        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 15);

        $paginator = $this->transferService->query($activeBranch, $user, $filters)->paginate($perPage);

        return $this->successResponse(
            data: CashTransferResource::collection($paginator),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        );
    }

    public function pendingCount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $activeBranch = app(ActiveBranch::class)->get();

        $count = $this->transferService->pendingCount($activeBranch, $user);

        return $this->successResponse(
            data: ['count' => $count],
        );
    }

    public function store(StoreCashTransferRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $activeBranch = app(ActiveBranch::class)->get();

        /** @var array{source_session_id: string, target_box_id: string, amount: numeric, reason: string} $data */
        $data = $request->validated();

        $transfer = $this->transferService->create($data, $user, $activeBranch);

        return $this->createdResponse(
            data: new CashTransferResource($transfer->load(['sourceBox', 'targetBox', 'sourceBranch', 'targetBranch', 'creator'])),
            message: 'Transferencia registrada correctamente.',
        );
    }

    public function show(string $id, Request $request): JsonResponse
    {
        $transfer = $this->transferService->findById($id);

        if ($transfer === null) {
            return $this->errorResponse("Transferencia no encontrada: {$id}", 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->assertTransferAccessible($user, $transfer);

        return $this->successResponse(
            data: new CashTransferResource($transfer),
        );
    }

    public function approve(string $id, Request $request): JsonResponse
    {
        $transfer = $this->transferService->findById($id);

        if ($transfer === null) {
            return $this->errorResponse("Transferencia no encontrada: {$id}", 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->assertTransferAccessible($user, $transfer);

        $approved = $this->transferService->approve($transfer, $user);

        return $this->successResponse(
            data: new CashTransferResource($approved),
            message: 'Transferencia aprobada correctamente.',
        );
    }

    public function reject(string $id, RejectCashTransferRequest $request): JsonResponse
    {
        $transfer = $this->transferService->findById($id);

        if ($transfer === null) {
            return $this->errorResponse("Transferencia no encontrada: {$id}", 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->assertTransferAccessible($user, $transfer);

        $rejected = $this->transferService->reject($transfer, $request->reason(), $user);

        return $this->successResponse(
            data: new CashTransferResource($rejected),
            message: 'Transferencia rechazada correctamente.',
        );
    }

    public function cancel(string $id, RejectCashTransferRequest $request): JsonResponse
    {
        $transfer = $this->transferService->findById($id);

        if ($transfer === null) {
            return $this->errorResponse("Transferencia no encontrada: {$id}", 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->assertTransferAccessible($user, $transfer);

        $cancelled = $this->transferService->cancel($transfer, $request->reason(), $user);

        return $this->successResponse(
            data: new CashTransferResource($cancelled),
            message: 'Transferencia cancelada correctamente.',
        );
    }

    private function assertTransferAccessible(User $user, CashTransfer $transfer): void
    {
        $accessibleIds = $this->branchAccessGuard->accessibleBranchIds($user);

        if (! in_array($transfer->source_branch_id, $accessibleIds, true) &&
            ! in_array($transfer->target_branch_id, $accessibleIds, true)) {
            abort(404, "Transferencia no encontrada: {$transfer->id}");
        }
    }
}
