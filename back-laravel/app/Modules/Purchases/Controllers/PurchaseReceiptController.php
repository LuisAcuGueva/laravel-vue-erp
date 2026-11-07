<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseReceipt;
use App\Modules\Purchases\Requests\CancelPurchaseReceiptRequest;
use App\Modules\Purchases\Requests\ConfirmPurchaseReceiptRequest;
use App\Modules\Purchases\Requests\ListPurchaseReceiptsRequest;
use App\Modules\Purchases\Requests\ReceiveAllRequest;
use App\Modules\Purchases\Requests\StorePurchaseReceiptRequest;
use App\Modules\Purchases\Requests\UpdatePurchaseReceiptRequest;
use App\Modules\Purchases\Resources\PurchaseReceiptResource;
use App\Modules\Purchases\Services\PurchaseOrderService;
use App\Modules\Purchases\Services\PurchaseReceiptConfirmationService;
use App\Modules\Purchases\Services\PurchaseReceiptService;
use App\Modules\Users\Models\User;
use App\Support\Branch\ActiveBranch;
use App\Support\Branch\BranchAccessGuard;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseReceiptController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PurchaseReceiptService $service,
        private readonly PurchaseReceiptConfirmationService $confirmationService,
        private readonly PurchaseOrderService $orderService,
        private readonly BranchAccessGuard $branchAccessGuard,
    ) {}

    public function pendingItems(string $id, Request $request): JsonResponse
    {
        $order = $this->resolveOrder($id, $request);

        return $this->successResponse(
            data: $this->service->pendingItems($order),
        );
    }

    public function receiveAll(string $id, ReceiveAllRequest $request): JsonResponse
    {
        $order = $this->resolveOrder($id, $request);

        /** @var User $user */
        $user = $request->user();

        ['receipt' => $receipt, 'warnings' => $warnings] = $this->confirmationService->receiveAll($order, $request->validated(), $user);

        return (new PurchaseReceiptResource($receipt))
            ->additional(['meta' => ['warnings' => $warnings]])
            ->response()
            ->setStatusCode(201);
    }

    public function index(ListPurchaseReceiptsRequest $request): JsonResponse
    {
        $activeBranchId = app(ActiveBranch::class)->id();

        $paginator = $this->service
            ->query($activeBranchId, $request->validated())
            ->paginate((int) $request->input('per_page', 15));

        return $this->successResponse(
            data: PurchaseReceiptResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(StorePurchaseReceiptRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $activeBranchId = app(ActiveBranch::class)->id();

        $receipt = $this->service->create($request->validated(), $user, $activeBranchId);

        return (new PurchaseReceiptResource($receipt))->response()->setStatusCode(201);
    }

    public function show(string $id, Request $request): JsonResponse|PurchaseReceiptResource
    {
        return new PurchaseReceiptResource($this->resolve($id, $request));
    }

    public function update(string $id, UpdatePurchaseReceiptRequest $request): JsonResponse|PurchaseReceiptResource
    {
        $receipt = $this->resolve($id, $request);

        /** @var User $user */
        $user = $request->user();

        $updated = $this->service->update($receipt, $request->validated(), $user);

        return new PurchaseReceiptResource($updated);
    }

    public function confirm(string $id, ConfirmPurchaseReceiptRequest $request): JsonResponse
    {
        $receipt = $this->resolve($id, $request);

        /** @var User $user */
        $user = $request->user();

        ['receipt' => $confirmed, 'warnings' => $warnings] = $this->confirmationService->confirm(
            $receipt,
            $user,
            $request->validated('installments'),
            $request->validated('down_payment'),
        );

        return (new PurchaseReceiptResource($confirmed))
            ->additional(['meta' => ['warnings' => $warnings]])
            ->response();
    }

    public function cancel(string $id, CancelPurchaseReceiptRequest $request): JsonResponse|PurchaseReceiptResource
    {
        $receipt = $this->resolve($id, $request);

        /** @var User $user */
        $user = $request->user();

        $cancelled = $this->confirmationService->cancel($receipt, (string) $request->validated('reason'), $user);

        return new PurchaseReceiptResource($cancelled);
    }

    private function resolve(string $id, Request $request): PurchaseReceipt
    {
        $receipt = $this->service->findById($id);

        if ($receipt === null) {
            abort(404, "Recepción de compra no encontrada: {$id}");
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $receipt->branch_id);

        return $receipt;
    }

    private function resolveOrder(string $id, Request $request): PurchaseOrder
    {
        $order = $this->orderService->findById($id);

        if ($order === null) {
            abort(404, "Orden de compra no encontrada: {$id}");
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $order->branch_id);

        return $order;
    }
}
