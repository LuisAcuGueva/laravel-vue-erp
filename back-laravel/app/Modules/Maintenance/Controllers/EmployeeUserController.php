<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Maintenance\Requests\CreateEmployeeUserRequest;
use App\Modules\Maintenance\Requests\LinkEmployeeUserRequest;
use App\Modules\Maintenance\Resources\EmployeeResource;
use App\Modules\Maintenance\Services\EmployeeService;
use App\Modules\Maintenance\Services\EmployeeUserService;
use App\Modules\Users\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class EmployeeUserController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly EmployeeService $employeeService,
        private readonly EmployeeUserService $employeeUserService,
    ) {}

    public function store(string $id, CreateEmployeeUserRequest $request): JsonResponse
    {
        $employee = $this->employeeService->findById($id);

        if ($employee === null) {
            return $this->errorResponse("Empleado no encontrado: {$id}", 404);
        }

        /** @var User $actor */
        $actor = $request->user();

        $updated = $this->employeeUserService->createUser(
            employee: $employee,
            userAttrs: $request->userAttributes(),
            roles: $request->roles(),
            branchIds: $request->branchIds(),
            actor: $actor,
        );

        return (new EmployeeResource($updated))
            ->response()
            ->setStatusCode(201);
    }

    public function update(string $id, LinkEmployeeUserRequest $request): JsonResponse|EmployeeResource
    {
        $employee = $this->employeeService->findById($id);

        if ($employee === null) {
            return $this->errorResponse("Empleado no encontrado: {$id}", 404);
        }

        /** @var User|null $user */
        $user = User::query()->find($request->userId());

        if ($user === null) {
            return $this->errorResponse("Usuario no encontrado: {$request->userId()}", 404);
        }

        $updated = $this->employeeUserService->link($employee, $user);

        return new EmployeeResource($updated);
    }

    public function destroy(string $id): JsonResponse|EmployeeResource
    {
        $employee = $this->employeeService->findById($id);

        if ($employee === null) {
            return $this->errorResponse("Empleado no encontrado: {$id}", 404);
        }

        $updated = $this->employeeUserService->unlink($employee);

        return new EmployeeResource($updated);
    }
}
