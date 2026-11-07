<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Purchases\Models\Provider;
use App\Modules\Purchases\Requests\ListPayablesRequest;
use App\Modules\Purchases\Requests\ProviderStatementRequest;
use App\Modules\Purchases\Resources\AgingResource;
use App\Modules\Purchases\Resources\PayableInstallmentResource;
use App\Modules\Purchases\Services\AccountsPayableQueryService;
use App\Support\Branch\ActiveBranch;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class AccountsPayableController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AccountsPayableQueryService $queryService,
    ) {}

    public function index(ListPayablesRequest $request): JsonResponse
    {
        $query = $this->queryService->openInstallments($request->filters(), app(ActiveBranch::class)->id());
        $totals = $this->queryService->totalsByCurrency($query);
        $paginator = $query->paginate((int) $request->input('per_page', 15));

        return $this->successResponse(
            data: PayableInstallmentResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'totals_by_currency' => $totals,
            ],
        );
    }

    public function aging(ListPayablesRequest $request): JsonResponse
    {
        $filters = $request->filters();
        $asOf = isset($filters['as_of']) ? (string) $filters['as_of'] : null;

        $rows = $this->queryService->aging(app(ActiveBranch::class)->id(), $asOf, $filters);

        return $this->successResponse(
            data: AgingResource::collection($rows),
            meta: ['as_of' => $asOf ?? today()->toDateString()],
        );
    }

    public function statement(string $id, ProviderStatementRequest $request): JsonResponse
    {
        /** @var Provider $provider */
        $provider = Provider::withTrashed()->findOrFail($id);

        return $this->successResponse(
            data: $this->queryService->statement(
                $provider,
                app(ActiveBranch::class)->id(),
                (string) $request->validated('date_from'),
                (string) $request->validated('date_to'),
                $request->validated('currency_id') !== null ? (string) $request->validated('currency_id') : null,
            ),
        );
    }
}
