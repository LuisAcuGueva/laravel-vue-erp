<?php

declare(strict_types=1);

namespace App\Modules\Roles\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Roles\Requests\ListRolesRequest;
use App\Modules\Roles\Requests\RoleRequest;
use App\Modules\Roles\Resources\RoleResource;
use App\Modules\Roles\Services\RoleService;
use App\Modules\Users\Models\User;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService,
    ) {}

    public function index(ListRolesRequest $request): JsonResponse
    {
        $paginator = $this->roleService->list($request->validated());

        return response()->json([
            'data' => RoleResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(RoleRequest $request): JsonResponse
    {
        $role = $this->roleService->create(
            $request->toAttributes(),
            $request->permissions() ?? [],
        );

        return (new RoleResource($role))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): JsonResponse|RoleResource
    {
        $role = $this->roleService->findById($id);

        if ($role === null) {
            return response()->json([
                'message' => "Rol no encontrado: {$id}",
            ], 404);
        }

        return new RoleResource($role);
    }

    public function update(string $id, RoleRequest $request): JsonResponse|RoleResource
    {
        $role = $this->roleService->findById($id);

        if ($role === null) {
            return response()->json([
                'message' => "Rol no encontrado: {$id}",
            ], 404);
        }

        /** @var User $causer */
        $causer = $request->user();

        $updated = $this->roleService->update(
            $role,
            $request->toAttributes(),
            $request->permissions(),
            $causer,
        );

        return new RoleResource($updated);
    }

    public function destroy(string $id): JsonResponse
    {
        $role = $this->roleService->findById($id);

        if ($role === null) {
            return response()->json([
                'message' => "Rol no encontrado: {$id}",
            ], 404);
        }

        $this->roleService->delete($role);

        return response()->json(null, 204);
    }
}
