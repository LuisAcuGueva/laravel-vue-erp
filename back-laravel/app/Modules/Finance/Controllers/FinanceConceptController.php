<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Enums\FinancialAccountType;
use App\Modules\Finance\Requests\FinanceConceptRequest;
use App\Modules\Finance\Resources\FinanceConceptResource;
use App\Modules\Finance\Services\FinanceConceptService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceConceptController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected FinanceConceptService $conceptService,
    ) {}

    public function options(Request $request): JsonResponse
    {
        $direction = $request->query('direction') ? (string) $request->query('direction') : null;
        $includeSystem = $request->boolean('include_system', false);

        $concepts = $this->conceptService->options($direction, $includeSystem, $this->accountType($request));

        return $this->successResponse(
            data: FinanceConceptResource::collection($concepts),
        );
    }

    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search') ? (string) $request->query('search') : null;
        $direction = $request->query('direction') ? (string) $request->query('direction') : null;
        $active = $request->has('is_active') ? $request->boolean('is_active') : null;
        $perPage = (int) $request->query('per_page', 15);

        $paginator = $this->conceptService->paginate(
            $search,
            $direction,
            $active,
            $perPage,
            $request->has('has_account') ? $request->boolean('has_account') : null,
            $this->accountType($request),
        );

        return $this->successResponse(
            data: FinanceConceptResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    private function accountType(Request $request): ?FinancialAccountType
    {
        return FinancialAccountType::tryFrom((string) $request->query('account_type'));
    }

    public function store(FinanceConceptRequest $request): JsonResponse
    {
        $concept = $this->conceptService->create($request->toAttributes());

        return $this->createdResponse(
            data: new FinanceConceptResource($concept),
        );
    }

    public function show(string $id): JsonResponse
    {
        $concept = $this->conceptService->find($id);

        return $this->successResponse(
            data: new FinanceConceptResource($concept),
        );
    }

    public function update(FinanceConceptRequest $request, string $id): JsonResponse
    {
        $concept = $this->conceptService->find($id);
        $updated = $this->conceptService->update($concept, $request->toAttributes());

        return $this->successResponse(
            data: new FinanceConceptResource($updated),
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $concept = $this->conceptService->find($id);
        $this->conceptService->delete($concept);

        return $this->noContentResponse();
    }
}
