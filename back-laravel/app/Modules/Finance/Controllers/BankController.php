<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Requests\BankRequest;
use App\Modules\Finance\Resources\BankResource;
use App\Modules\Finance\Services\BankService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected BankService $bankService,
    ) {}

    public function options(): JsonResponse
    {
        $banks = $this->bankService->options();

        return $this->successResponse(
            data: BankResource::collection($banks),
        );
    }

    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search') ? (string) $request->query('search') : null;
        $active = $request->has('is_active') ? $request->boolean('is_active') : null;
        $perPage = (int) $request->query('per_page', 15);

        $paginator = $this->bankService->paginate($search, $active, $perPage);

        return $this->successResponse(
            data: BankResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(BankRequest $request): JsonResponse
    {
        $bank = $this->bankService->create($request->toAttributes());

        return $this->createdResponse(
            data: new BankResource($bank),
        );
    }

    public function show(string $id): JsonResponse
    {
        $bank = $this->bankService->find($id);

        return $this->successResponse(
            data: new BankResource($bank),
        );
    }

    public function update(BankRequest $request, string $id): JsonResponse
    {
        $bank = $this->bankService->find($id);
        $updated = $this->bankService->update($bank, $request->toAttributes());

        return $this->successResponse(
            data: new BankResource($updated),
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $bank = $this->bankService->find($id);
        $this->bankService->delete($bank);

        return $this->noContentResponse();
    }
}
