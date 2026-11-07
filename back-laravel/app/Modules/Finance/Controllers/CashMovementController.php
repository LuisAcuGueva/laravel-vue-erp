<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Enums\CashSessionStatus;
use App\Modules\Finance\Models\CashMovement;
use App\Modules\Finance\Requests\CancelCashMovementRequest;
use App\Modules\Finance\Requests\StoreCashMovementRequest;
use App\Modules\Finance\Resources\CashMovementResource;
use App\Modules\Finance\Services\BoxAccessService;
use App\Modules\Finance\Services\CashLedgerService;
use App\Modules\Finance\Services\CashSessionService;
use App\Modules\Users\Models\User;
use App\Support\Branch\BranchAccessGuard;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashMovementController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CashSessionService $cashSessionService,
        private readonly CashLedgerService $cashLedgerService,
        private readonly BoxAccessService $boxAccessService,
        private readonly BranchAccessGuard $branchAccessGuard,
    ) {}

    public function index(string $id, Request $request): JsonResponse
    {
        $session = $this->cashSessionService->findById($id);

        if ($session === null) {
            return $this->errorResponse("Sesión de caja no encontrada: {$id}", 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $session->branch_id);

        if (! $user->can('cash_sessions.view') && ! $user->isAdmin()) {
            if ($session->box === null || ! $this->boxAccessService->canOperate($user, $session->box)) {
                return $this->errorResponse('No tiene permisos para ver movimientos de esta sesión.', 403);
            }
        }

        $perPage = (int) $request->query('per_page', 20);
        $paginator = CashMovement::query()
            ->where('cash_session_id', $session->id)
            ->with(['concept', 'paymentMethod', 'creator', 'reversedBy'])
            ->orderByDesc('moved_at')
            ->paginate(perPage: $perPage);

        return $this->successResponse(
            data: CashMovementResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(string $id, StoreCashMovementRequest $request): JsonResponse
    {
        $session = $this->cashSessionService->findById($id);

        if ($session === null) {
            return $this->errorResponse("Sesión de caja no encontrada: {$id}", 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $session->branch_id);

        if ($session->box === null || ! $this->boxAccessService->canOperate($user, $session->box)) {
            return $this->errorResponse('No tiene permisos para operar esta caja.', 403);
        }

        $statusVal = $session->status instanceof CashSessionStatus
            ? $session->status
            : CashSessionStatus::tryFrom((string) $session->status);

        if ($statusVal !== CashSessionStatus::Open) {
            return $this->errorResponse('La sesión de caja no está abierta.', 422);
        }

        $data = $request->toMovementData($session->id);

        /** @var CashMovement $movement */
        $movement = DB::transaction(fn (): CashMovement => $this->cashLedgerService->register($data));

        return (new CashMovementResource($movement->load(['concept', 'paymentMethod', 'creator'])))
            ->response()
            ->setStatusCode(201);
    }

    public function cancel(string $id, CancelCashMovementRequest $request): JsonResponse
    {
        /** @var CashMovement|null $movement */
        $movement = CashMovement::query()->with('session.box')->find($id);

        if ($movement === null) {
            return $this->errorResponse("Movimiento no encontrado: {$id}", 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $movement->branch_id);

        if (! $movement->is_manual) {
            return $this->errorResponse('Solo se pueden anular movimientos manuales de caja desde este endpoint.', 422);
        }

        if ($movement->session === null) {
            return $this->errorResponse('Sesión no encontrada.', 404);
        }

        $sessionStatus = $movement->session->status instanceof CashSessionStatus
            ? $movement->session->status
            : CashSessionStatus::tryFrom((string) $movement->session->status);

        if ($sessionStatus !== CashSessionStatus::Open) {
            return $this->errorResponse('La sesión del movimiento ya está cerrada.', 422);
        }

        /** @var CashMovement $reversal */
        $reversal = DB::transaction(
            fn (): CashMovement => $this->cashLedgerService->reverse($movement, $movement, $movement->id, $request->reason())
        );

        return $this->successResponse(
            data: new CashMovementResource($reversal->load(['concept', 'paymentMethod', 'creator'])),
        );
    }
}
