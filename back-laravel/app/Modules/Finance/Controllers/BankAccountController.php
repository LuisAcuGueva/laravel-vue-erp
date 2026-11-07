<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Requests\BankAccountRequest;
use App\Modules\Finance\Resources\BankAccountResource;
use App\Modules\Finance\Services\BankAccountService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected BankAccountService $bankAccountService,
    ) {}

    public function options(Request $request): JsonResponse
    {
        $branchId = $request->query('branch_id') ? (string) $request->query('branch_id') : null;
        $accounts = $this->bankAccountService->options($branchId);

        return $this->successResponse(
            data: BankAccountResource::collection($accounts),
        );
    }

    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search') ? (string) $request->query('search') : null;
        $bankId = $request->query('bank_id') ? (string) $request->query('bank_id') : null;
        $currencyId = $request->query('currency_id') ? (string) $request->query('currency_id') : null;
        $branchId = $request->query('branch_id') ? (string) $request->query('branch_id') : null;
        $active = $request->has('is_active') ? $request->boolean('is_active') : null;
        $perPage = (int) $request->query('per_page', 15);

        $paginator = $this->bankAccountService->paginate(
            $search,
            $bankId,
            $currencyId,
            $branchId,
            $active,
            $perPage
        );

        return $this->successResponse(
            data: BankAccountResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(BankAccountRequest $request): JsonResponse
    {
        $account = $this->bankAccountService->create($request->toAttributes());

        return $this->createdResponse(
            data: new BankAccountResource($account),
        );
    }

    public function show(string $id): JsonResponse
    {
        $account = $this->bankAccountService->find($id);

        return $this->successResponse(
            data: new BankAccountResource($account),
        );
    }

    public function update(BankAccountRequest $request, string $id): JsonResponse
    {
        $account = $this->bankAccountService->find($id);
        $updated = $this->bankAccountService->update($account, $request->toAttributes());

        return $this->successResponse(
            data: new BankAccountResource($updated),
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $account = $this->bankAccountService->find($id);
        $this->bankAccountService->delete($account);

        return $this->noContentResponse();
    }
}
