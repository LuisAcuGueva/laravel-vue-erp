<?php

declare(strict_types=1);

namespace App\Modules\Permissions\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Permissions\Requests\ListPermissionsRequest;
use App\Modules\Permissions\Requests\PermissionRequest;
use App\Modules\Permissions\Resources\PermissionResource;
use App\Modules\Permissions\Services\PermissionService;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    public function index(ListPermissionsRequest $request): JsonResponse
    {
        $paginator = $this->permissionService->list($request->validated());

        return response()->json([
            'data' => PermissionResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(PermissionRequest $request): JsonResponse
    {
        $permission = $this->permissionService->create($request->toAttributes());

        return (new PermissionResource($permission))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): JsonResponse|PermissionResource
    {
        $permission = $this->permissionService->findById($id);

        if ($permission === null) {
            return response()->json([
                'message' => "Permiso no encontrado: {$id}",
            ], 404);
        }

        return new PermissionResource($permission);
    }

    public function update(string $id, PermissionRequest $request): JsonResponse|PermissionResource
    {
        $permission = $this->permissionService->findById($id);

        if ($permission === null) {
            return response()->json([
                'message' => "Permiso no encontrado: {$id}",
            ], 404);
        }

        $updated = $this->permissionService->update($permission, $request->toAttributes());

        return new PermissionResource($updated);
    }

    public function destroy(string $id): JsonResponse
    {
        $permission = $this->permissionService->findById($id);

        if ($permission === null) {
            return response()->json([
                'message' => "Permiso no encontrado: {$id}",
            ], 404);
        }

        $this->permissionService->delete($permission);

        return response()->json(null, 204);
    }
}
