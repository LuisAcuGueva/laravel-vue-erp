<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Maintenance\Exports\BranchCsvExport;
use App\Modules\Maintenance\Requests\BranchRequest;
use App\Modules\Maintenance\Requests\ListBranchesRequest;
use App\Modules\Maintenance\Resources\BranchOptionResource;
use App\Modules\Maintenance\Resources\BranchResource;
use App\Modules\Maintenance\Services\BranchService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BranchController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly BranchService $branchService,
    ) {}

    public function options(): JsonResponse
    {
        $branches = $this->branchService->options();

        return $this->successResponse(
            data: BranchOptionResource::collection($branches),
        );
    }

    public function index(ListBranchesRequest $request): JsonResponse
    {
        $paginator = $this->branchService->list($request->validated());

        return $this->successResponse(
            data: BranchResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function export(ListBranchesRequest $request): StreamedResponse
    {
        return app(BranchCsvExport::class)->download(
            $this->branchService->query($request->validated()),
            'sedes',
        );
    }

    public function store(BranchRequest $request): JsonResponse
    {
        $branch = $this->branchService->create($request->toAttributes());

        return (new BranchResource($branch))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): JsonResponse|BranchResource
    {
        $branch = $this->branchService->findById($id);

        if ($branch === null) {
            return $this->errorResponse("Sede no encontrada: {$id}", 404);
        }

        return new BranchResource($branch);
    }

    public function update(string $id, BranchRequest $request): JsonResponse|BranchResource
    {
        $branch = $this->branchService->findById($id);

        if ($branch === null) {
            return $this->errorResponse("Sede no encontrada: {$id}", 404);
        }

        $updated = $this->branchService->update($branch, $request->toAttributes());

        return new BranchResource($updated);
    }

    public function destroy(string $id): JsonResponse
    {
        $branch = $this->branchService->findById($id);

        if ($branch === null) {
            return $this->errorResponse("Sede no encontrada: {$id}", 404);
        }

        $this->branchService->delete($branch);

        return $this->noContentResponse();
    }
}
