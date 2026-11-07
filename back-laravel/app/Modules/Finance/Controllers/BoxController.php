<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Requests\BoxRequest;
use App\Modules\Finance\Requests\ListBoxesRequest;
use App\Modules\Finance\Requests\SyncBoxRolesRequest;
use App\Modules\Finance\Resources\BoxResource;
use App\Modules\Finance\Services\BoxAccessService;
use App\Modules\Finance\Services\BoxService;
use App\Modules\Maintenance\Services\UserBranchService;
use App\Modules\Users\Models\User;
use App\Support\Branch\ActiveBranch;
use App\Support\Branch\BranchAccessGuard;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoxController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly BoxService $boxService,
        private readonly BoxAccessService $boxAccessService,
        private readonly BranchAccessGuard $branchAccessGuard,
        private readonly UserBranchService $userBranchService,
    ) {}

    public function available(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($request->query('scope') === 'all') {
            $accessibleBranches = $this->userBranchService->accessibleBranches($user);
            $boxes = $this->boxAccessService->allAccessibleBoxes($user, $accessibleBranches);
        } else {
            $activeBranch = app(ActiveBranch::class)->get();
            $boxes = $this->boxAccessService->availableBoxes($user, $activeBranch);
        }

        return $this->successResponse(
            data: BoxResource::collection($boxes),
        );
    }

    public function suggestedOpening(string $id, Request $request): JsonResponse
    {
        $box = $this->boxService->findById($id);

        if ($box === null) {
            return $this->errorResponse("Caja no encontrada: {$id}", 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $box->branch_id);

        if (! $this->boxAccessService->canOperate($user, $box)) {
            return $this->errorResponse('No tiene permisos para operar esta caja.', 403);
        }

        $amount = $this->boxService->suggestedOpening($box);

        return $this->successResponse([
            'suggested_amount' => $amount,
            'suggested_opening' => $amount,
        ]);
    }

    public function index(ListBoxesRequest $request): JsonResponse
    {
        $paginator = $this->boxService->list($this->scopedFilters($request));

        return $this->successResponse(
            data: BoxResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(BoxRequest $request): JsonResponse
    {
        $box = $this->boxService->create($request->toAttributes());

        return (new BoxResource($box))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id, Request $request): JsonResponse|BoxResource
    {
        $box = $this->boxService->findById($id);

        if ($box === null) {
            return $this->errorResponse("Caja no encontrada: {$id}", 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $box->branch_id);

        return new BoxResource($box);
    }

    public function update(string $id, BoxRequest $request): JsonResponse|BoxResource
    {
        $box = $this->boxService->findById($id);

        if ($box === null) {
            return $this->errorResponse("Caja no encontrada: {$id}", 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $box->branch_id);

        $updated = $this->boxService->update($box, $request->toAttributes());

        return new BoxResource($updated);
    }

    public function destroy(string $id, Request $request): JsonResponse
    {
        $box = $this->boxService->findById($id);

        if ($box === null) {
            return $this->errorResponse("Caja no encontrada: {$id}", 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $box->branch_id);

        $this->boxService->delete($box);

        return $this->noContentResponse();
    }

    public function syncRoles(string $id, SyncBoxRolesRequest $request): JsonResponse|BoxResource
    {
        $box = $this->boxService->findById($id);

        if ($box === null) {
            return $this->errorResponse("Caja no encontrada: {$id}", 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $box->branch_id);

        $updated = $this->boxService->syncRoles($box, $request->roleIds());

        return new BoxResource($updated);
    }

    /**
     * @return array<string, mixed>
     */
    private function scopedFilters(ListBoxesRequest $request): array
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
