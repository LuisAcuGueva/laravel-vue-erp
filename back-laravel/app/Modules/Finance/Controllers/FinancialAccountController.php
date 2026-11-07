<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Requests\FinancialAccountRequest;
use App\Modules\Finance\Resources\FinancialAccountResource;
use App\Modules\Finance\Services\FinancialAccountService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialAccountController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected FinancialAccountService $accountService,
    ) {}

    public function tree(): JsonResponse
    {
        $roots = $this->accountService->tree();

        return $this->successResponse(
            data: FinancialAccountResource::collection($roots),
        );
    }

    public function options(Request $request): JsonResponse
    {
        $type = $request->query('type') ? (string) $request->query('type') : null;
        $postableOnly = $request->boolean('postable', false);

        $accounts = $this->accountService->options($type, $postableOnly);

        return $this->successResponse(
            data: FinancialAccountResource::collection($accounts),
        );
    }

    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search') ? (string) $request->query('search') : null;
        $type = $request->query('type') ? (string) $request->query('type') : null;
        $postable = $request->has('is_postable') ? $request->boolean('is_postable') : null;
        $active = $request->has('is_active') ? $request->boolean('is_active') : null;
        $perPage = (int) $request->query('per_page', 15);

        $paginator = $this->accountService->paginate(
            $search,
            $type,
            $postable,
            $active,
            $perPage
        );

        return $this->successResponse(
            data: FinancialAccountResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(FinancialAccountRequest $request): JsonResponse
    {
        $account = $this->accountService->create($request->toAttributes());

        return $this->createdResponse(
            data: new FinancialAccountResource($account),
        );
    }

    public function show(string $id): JsonResponse
    {
        $account = $this->accountService->find($id);

        return $this->successResponse(
            data: new FinancialAccountResource($account),
        );
    }

    public function update(FinancialAccountRequest $request, string $id): JsonResponse
    {
        $account = $this->accountService->find($id);
        $updated = $this->accountService->update($account, $request->toAttributes());

        return $this->successResponse(
            data: new FinancialAccountResource($updated),
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $account = $this->accountService->find($id);
        $this->accountService->delete($account);

        return $this->noContentResponse();
    }
}
