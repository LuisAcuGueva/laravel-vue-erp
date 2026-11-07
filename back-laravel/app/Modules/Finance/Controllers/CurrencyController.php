<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Requests\CurrencyRequest;
use App\Modules\Finance\Resources\CurrencyResource;
use App\Modules\Finance\Services\CurrencyService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CurrencyService $currencyService,
    ) {}

    public function options(): JsonResponse
    {
        $currencies = $this->currencyService->options();

        return $this->successResponse(
            data: CurrencyResource::collection($currencies),
        );
    }

    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search') ? (string) $request->query('search') : null;
        $active = $request->has('is_active') ? $request->boolean('is_active') : null;
        $perPage = (int) $request->query('per_page', 15);

        $paginator = $this->currencyService->paginate($search, $active, $perPage);

        return $this->successResponse(
            data: CurrencyResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(CurrencyRequest $request): JsonResponse
    {
        $currency = $this->currencyService->create($request->toAttributes());

        return $this->createdResponse(
            data: new CurrencyResource($currency),
        );
    }

    public function show(string $id): JsonResponse
    {
        $currency = $this->currencyService->find($id);

        return $this->successResponse(
            data: new CurrencyResource($currency),
        );
    }

    public function update(CurrencyRequest $request, string $id): JsonResponse
    {
        $currency = $this->currencyService->find($id);
        $updated = $this->currencyService->update($currency, $request->toAttributes());

        return $this->successResponse(
            data: new CurrencyResource($updated),
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $currency = $this->currencyService->find($id);
        $this->currencyService->delete($currency);

        return $this->noContentResponse();
    }
}
