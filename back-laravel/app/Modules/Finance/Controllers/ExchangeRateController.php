<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Requests\ListExchangeRatesRequest;
use App\Modules\Finance\Requests\UpsertExchangeRateRequest;
use App\Modules\Finance\Resources\ExchangeRateResource;
use App\Modules\Finance\Services\ExchangeRateService;
use App\Modules\Users\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ExchangeRateService $exchangeRateService,
    ) {}

    public function current(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'currency_id' => ['required', 'uuid', 'exists:currencies,id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $currencyId = (string) $validated['currency_id'];
        $date = isset($validated['date']) ? (string) $validated['date'] : null;

        $result = $this->exchangeRateService->current($currencyId, $date);

        return $this->successResponse(
            data: [
                'rate' => $result['rate'] ? new ExchangeRateResource($result['rate']) : null,
                'is_stale' => $result['is_stale'],
            ],
        );
    }

    public function upsert(UpsertExchangeRateRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        $rate = $this->exchangeRateService->upsert(
            (string) $request->validated('currency_id'),
            (string) $request->validated('date'),
            (float) $request->validated('buy_rate'),
            (float) $request->validated('sell_rate'),
            $user
        );

        return $this->successResponse(
            data: new ExchangeRateResource($rate),
            message: 'Tipo de cambio registrado exitosamente',
        );
    }

    public function index(ListExchangeRatesRequest $request): JsonResponse
    {
        $paginator = $this->exchangeRateService->list(
            $request->validated('currency_id'),
            $request->validated('date_from'),
            $request->validated('date_to'),
            (int) ($request->validated('per_page') ?? 30)
        );

        return $this->successResponse(
            data: ExchangeRateResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }
}
