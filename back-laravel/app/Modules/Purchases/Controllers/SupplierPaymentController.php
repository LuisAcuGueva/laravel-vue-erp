<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Purchases\Models\SupplierPayment;
use App\Modules\Purchases\Requests\CancelSupplierDocumentRequest;
use App\Modules\Purchases\Requests\ListSupplierPaymentsRequest;
use App\Modules\Purchases\Requests\OpenInstallmentsRequest;
use App\Modules\Purchases\Requests\StoreSupplierPaymentRequest;
use App\Modules\Purchases\Resources\PayableInstallmentResource;
use App\Modules\Purchases\Resources\SupplierPaymentResource;
use App\Modules\Purchases\Services\AccountsPayableQueryService;
use App\Modules\Purchases\Services\SupplierPaymentService;
use App\Modules\Users\Models\User;
use App\Support\Branch\ActiveBranch;
use App\Support\Branch\BranchAccessGuard;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierPaymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly SupplierPaymentService $service,
        private readonly AccountsPayableQueryService $queryService,
        private readonly BranchAccessGuard $branchAccessGuard,
    ) {}

    public function index(ListSupplierPaymentsRequest $request): JsonResponse
    {
        $paginator = $this->service
            ->query($request->validated(), app(ActiveBranch::class)->id())
            ->paginate((int) $request->input('per_page', 15));

        return $this->successResponse(
            data: SupplierPaymentResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function openInstallments(OpenInstallmentsRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $filters */
        $filters = $request->validated();

        $installments = $this->queryService
            ->openInstallments($filters, app(ActiveBranch::class)->id())
            ->when(empty($filters['provider_id']), fn ($q) => $q->whereNull('provider_id'))
            ->limit(500)
            ->get();

        return $this->successResponse(data: PayableInstallmentResource::collection($installments));
    }

    public function store(StoreSupplierPaymentRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $payment = $this->service->create($request->toAttributes(), $user, app(ActiveBranch::class)->get());

        return $this->createdResponse(
            data: new SupplierPaymentResource($payment),
            message: 'Pago a proveedor registrado correctamente.',
        );
    }

    public function show(string $id, Request $request): JsonResponse
    {
        return $this->successResponse(data: new SupplierPaymentResource($this->resolve($id, $request)));
    }

    public function cancel(string $id, CancelSupplierDocumentRequest $request): JsonResponse
    {
        $payment = $this->resolve($id, $request);

        /** @var User $user */
        $user = $request->user();

        $cancelled = $this->service->cancel($payment, (string) $request->validated('reason'), $user);

        return $this->successResponse(
            data: new SupplierPaymentResource($cancelled),
            message: 'Pago a proveedor anulado correctamente.',
        );
    }

    private function resolve(string $id, Request $request): SupplierPayment
    {
        $payment = $this->service->findById($id);

        if ($payment === null) {
            abort(404, "Pago a proveedor no encontrado: {$id}");
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $payment->branch_id);

        return $payment;
    }
}
