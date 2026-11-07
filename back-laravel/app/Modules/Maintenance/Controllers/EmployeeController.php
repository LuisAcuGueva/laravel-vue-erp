<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Maintenance\Exports\EmployeeCsvExport;
use App\Modules\Maintenance\Requests\EmployeeRequest;
use App\Modules\Maintenance\Requests\ListEmployeesRequest;
use App\Modules\Maintenance\Resources\EmployeeOptionResource;
use App\Modules\Maintenance\Resources\EmployeeResource;
use App\Modules\Maintenance\Services\EmployeeService;
use App\Modules\Users\Models\User;
use App\Support\Branch\BranchAccessGuard;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly EmployeeService $employeeService,
    ) {}

    public function options(Request $request): JsonResponse
    {
        $branchId = $request->query('branch_id');
        $search = $request->query('search');

        $employees = $this->employeeService->options(
            branchId: is_string($branchId) && $branchId !== '' ? $branchId : null,
            search: is_string($search) && $search !== '' ? $search : null,
        );

        return $this->successResponse(
            data: EmployeeOptionResource::collection($employees),
        );
    }

    public function index(ListEmployeesRequest $request): JsonResponse
    {
        $paginator = $this->employeeService->list($request->validated());

        return $this->successResponse(
            data: EmployeeResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function export(ListEmployeesRequest $request, BranchAccessGuard $branchAccessGuard): StreamedResponse
    {
        $filters = $request->validated();

        // Regla multi-sede: un branch_id del query solo se acepta si la sede es accesible (404 si no).
        if (! empty($filters['branch_id'])) {
            /** @var User $user */
            $user = $request->user();
            $branchAccessGuard->assertAccessible($user, (string) $filters['branch_id']);
        }

        return app(EmployeeCsvExport::class)->download(
            $this->employeeService->query($filters),
            'empleados',
        );
    }

    public function store(EmployeeRequest $request): JsonResponse
    {
        $employee = $this->employeeService->create($request->toAttributes());
        $employee->load(['area', 'branch', 'user']);

        return (new EmployeeResource($employee))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): JsonResponse|EmployeeResource
    {
        $employee = $this->employeeService->findById($id);

        if ($employee === null) {
            return $this->errorResponse("Empleado no encontrado: {$id}", 404);
        }

        return new EmployeeResource($employee);
    }

    public function update(string $id, EmployeeRequest $request): JsonResponse|EmployeeResource
    {
        $employee = $this->employeeService->findById($id);

        if ($employee === null) {
            return $this->errorResponse("Empleado no encontrado: {$id}", 404);
        }

        $updated = $this->employeeService->update($employee, $request->toAttributes());

        return new EmployeeResource($updated);
    }

    public function destroy(string $id): JsonResponse
    {
        $employee = $this->employeeService->findById($id);

        if ($employee === null) {
            return $this->errorResponse("Empleado no encontrado: {$id}", 404);
        }

        $this->employeeService->delete($employee);

        return $this->noContentResponse();
    }
}
