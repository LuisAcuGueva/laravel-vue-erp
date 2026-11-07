<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Requests\PaymentMethodRequest;
use App\Modules\Finance\Resources\PaymentMethodResource;
use App\Modules\Finance\Services\PaymentMethodService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PaymentMethodService $paymentMethodService,
    ) {}

    public function options(): JsonResponse
    {
        $methods = $this->paymentMethodService->options();

        return $this->successResponse(
            data: PaymentMethodResource::collection($methods),
        );
    }

    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search') ? (string) $request->query('search') : null;
        $kind = $request->query('kind') ? (string) $request->query('kind') : null;
        $settlement = $request->query('settlement') ? (string) $request->query('settlement') : null;
        $active = $request->has('is_active') ? $request->boolean('is_active') : null;
        $perPage = (int) $request->query('per_page', 15);

        $paginator = $this->paymentMethodService->paginate(
            $search,
            $kind,
            $settlement,
            $active,
            $perPage
        );

        return $this->successResponse(
            data: PaymentMethodResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(PaymentMethodRequest $request): JsonResponse
    {
        $method = $this->paymentMethodService->create($request->toAttributes());

        return $this->createdResponse(
            data: new PaymentMethodResource($method),
        );
    }

    public function show(string $id): JsonResponse
    {
        $method = $this->paymentMethodService->find($id);

        return $this->successResponse(
            data: new PaymentMethodResource($method),
        );
    }

    public function update(PaymentMethodRequest $request, string $id): JsonResponse
    {
        $method = $this->paymentMethodService->find($id);
        $updated = $this->paymentMethodService->update($method, $request->toAttributes());

        return $this->successResponse(
            data: new PaymentMethodResource($updated),
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $method = $this->paymentMethodService->find($id);
        $this->paymentMethodService->delete($method);

        return $this->noContentResponse();
    }
}
