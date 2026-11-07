<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Requests\CloseCashSessionRequest;
use App\Modules\Finance\Requests\ListCashSessionsRequest;
use App\Modules\Finance\Requests\OpenCashSessionRequest;
use App\Modules\Finance\Requests\ReviewCashSessionRequest;
use App\Modules\Finance\Resources\CashSessionResource;
use App\Modules\Finance\Services\BoxAccessService;
use App\Modules\Finance\Services\BoxService;
use App\Modules\Finance\Services\CashSessionService;
use App\Modules\Finance\Services\CashSessionSummaryService;
use App\Modules\Users\Models\User;
use App\Support\Branch\ActiveBranch;
use App\Support\Branch\BranchAccessGuard;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashSessionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CashSessionService $cashSessionService,
        private readonly CashSessionSummaryService $summaryService,
        private readonly BoxService $boxService,
        private readonly BoxAccessService $boxAccessService,
        private readonly BranchAccessGuard $branchAccessGuard,
    ) {}

    public function index(ListCashSessionsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $filters = $request->validated();

        if (! empty($filters['branch_id'])) {
            $this->branchAccessGuard->assertAccessible($user, (string) $filters['branch_id']);
        }

        $filters['branch_ids'] = $this->branchAccessGuard->accessibleBranchIds($user);

        // Si solo tiene permiso operate y no view, restringir a las cajas que puede operar
        if (! $user->can('cash_sessions.view') && ! $user->isAdmin()) {
            $activeBranch = app(ActiveBranch::class)->get();
            $availableBoxes = $this->boxAccessService->availableBoxes($user, $activeBranch);
            $filters['box_ids'] = $availableBoxes->pluck('id')->all();
        }

        $paginator = $this->cashSessionService->list($filters);

        return $this->successResponse(
            data: CashSessionResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function open(OpenCashSessionRequest $request): JsonResponse
    {
        $box = $this->boxService->findById($request->boxId());

        if ($box === null) {
            return $this->errorResponse("Caja no encontrada: {$request->boxId()}", 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $box->branch_id);

        $activeBranch = app(ActiveBranch::class)->get();
        if ($box->branch_id !== $activeBranch->id) {
            return $this->errorResponse('La caja pertenece a una sede diferente de la activa.', 403);
        }

        if (! $this->boxAccessService->canOperate($user, $box)) {
            return $this->errorResponse('No tiene permisos para operar esta caja.', 403);
        }

        $session = $this->cashSessionService->open(
            $box,
            $request->openingAmount(),
            $request->openingNotes(),
            $user
        );

        return (new CashSessionResource($session))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id, Request $request): JsonResponse|CashSessionResource
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
                return $this->errorResponse('No tiene permisos para consultar esta sesión de caja.', 403);
            }
        }

        $summary = $this->summaryService->summary($session);

        return (new CashSessionResource($session))->withSummary($summary);
    }

    public function close(string $id, CloseCashSessionRequest $request): JsonResponse|CashSessionResource
    {
        $session = $this->cashSessionService->findById($id);

        if ($session === null) {
            return $this->errorResponse("Sesión de caja no encontrada: {$id}", 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $session->branch_id);

        if ($session->box === null || ! $this->boxAccessService->canOperate($user, $session->box)) {
            return $this->errorResponse('No tiene permisos para cerrar esta sesión de caja.', 403);
        }

        $closedSession = $this->cashSessionService->close(
            $session,
            $request->countDetails(),
            $request->countedCash(),
            $request->closingNotes(),
            $user
        );

        $summary = $this->summaryService->summary($closedSession);

        return (new CashSessionResource($closedSession))->withSummary($summary);
    }

    public function review(string $id, ReviewCashSessionRequest $request): JsonResponse|CashSessionResource
    {
        $session = $this->cashSessionService->findById($id);

        if ($session === null) {
            return $this->errorResponse("Sesión de caja no encontrada: {$id}", 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $session->branch_id);

        $reviewedSession = $this->cashSessionService->review(
            $session,
            $request->decision(),
            $request->notes(),
            $user
        );

        return new CashSessionResource($reviewedSession);
    }
}
