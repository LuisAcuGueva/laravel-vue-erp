<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Maintenance\Requests\SyncUserBranchesRequest;
use App\Modules\Maintenance\Services\UserBranchService;
use App\Modules\Users\Models\User;
use App\Modules\Users\Resources\UserResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class UserBranchController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly UserBranchService $userBranchService,
    ) {}

    public function update(string $id, SyncUserBranchesRequest $request): JsonResponse|UserResource
    {
        $user = User::find($id);

        if ($user === null) {
            return $this->errorResponse("Usuario no encontrado: {$id}", 404);
        }

        /** @var array<int, string> $branchIds */
        $branchIds = (array) $request->input('branch_ids');
        /** @var string $defaultBranchId */
        $defaultBranchId = (string) $request->input('default_branch_id');

        /** @var User $causer */
        $causer = $request->user();

        $updatedUser = $this->userBranchService->sync($user, $branchIds, $defaultBranchId, $causer);

        return new UserResource($updatedUser);
    }
}
