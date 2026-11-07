<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Requests\ListPurchaseOrdersRequest;
use App\Modules\Purchases\Requests\RejectPurchaseDocumentRequest;
use App\Modules\Purchases\Requests\StorePurchaseOrderRequest;
use App\Modules\Purchases\Resources\PurchaseOrderResource;
use App\Modules\Purchases\Services\PurchaseOrderPdfBuilder;
use App\Modules\Purchases\Services\PurchaseOrderService;
use App\Modules\Purchases\Services\PurchaseOrderWorkflowService;
use App\Modules\Users\Models\User;
use App\Support\Branch\ActiveBranch;
use App\Support\Branch\BranchAccessGuard;
use App\Support\Http\ApiResponse;
use App\Support\Pdf\PdfRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PurchaseOrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PurchaseOrderService $service,
        private readonly PurchaseOrderWorkflowService $workflow,
        private readonly BranchAccessGuard $branchAccessGuard,
    ) {}

    public function index(ListPurchaseOrdersRequest $request): JsonResponse
    {
        $paginator = $this->service
            ->query($request->validated(), app(ActiveBranch::class)->id())
            ->paginate((int) $request->input('per_page', 15));

        return $this->successResponse(
            data: PurchaseOrderResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $order = $this->service->create($request->toAttributes(), $user, app(ActiveBranch::class)->get());

        return (new PurchaseOrderResource($order))->response()->setStatusCode(201);
    }

    public function show(string $id, Request $request): JsonResponse|PurchaseOrderResource
    {
        return new PurchaseOrderResource($this->resolve($id, $request));
    }

    public function update(string $id, StorePurchaseOrderRequest $request): JsonResponse|PurchaseOrderResource
    {
        /** @var User $user */
        $user = $request->user();

        return new PurchaseOrderResource($this->service->update($this->resolve($id, $request), $request->toAttributes(), $user));
    }

    public function submit(string $id, Request $request): JsonResponse|PurchaseOrderResource
    {
        /** @var User $user */
        $user = $request->user();

        return new PurchaseOrderResource($this->workflow->submit($this->resolve($id, $request), $user));
    }

    public function approve(string $id, Request $request): JsonResponse|PurchaseOrderResource
    {
        /** @var User $user */
        $user = $request->user();

        return new PurchaseOrderResource($this->workflow->approve($this->resolve($id, $request), $user));
    }

    public function reject(string $id, RejectPurchaseDocumentRequest $request): JsonResponse|PurchaseOrderResource
    {
        /** @var User $user */
        $user = $request->user();

        return new PurchaseOrderResource($this->workflow->reject($this->resolve($id, $request), $request->reason(), $user));
    }

    public function close(string $id, Request $request): JsonResponse|PurchaseOrderResource
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return new PurchaseOrderResource($this->workflow->close($this->resolve($id, $request), isset($data['reason']) ? trim((string) $data['reason']) : null, $user));
    }

    public function cancel(string $id, RejectPurchaseDocumentRequest $request): JsonResponse|PurchaseOrderResource
    {
        /** @var User $user */
        $user = $request->user();

        return new PurchaseOrderResource($this->workflow->cancel($this->resolve($id, $request), $request->reason(), $user));
    }

    /**
     * PDF A4 de la OC. Las órdenes aún no emitidas (borrador, pendiente, rechazada) llevan marca de agua.
     */
    public function pdf(string $id, Request $request, PdfRenderer $renderer, PurchaseOrderPdfBuilder $builder): Response
    {
        $order = $this->resolve($id, $request);

        return $renderer->render('pdf.purchases.order', $builder->build($order), [
            'filename' => $order->number.'.pdf',
        ]);
    }

    /**
     * Carga la OC y exige que su sede sea accesible para el usuario (404 si no).
     */
    private function resolve(string $id, Request $request): PurchaseOrder
    {
        $order = $this->service->findById($id);

        if ($order === null) {
            abort(404, "Orden de compra no encontrada: {$id}");
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $order->branch_id);

        return $order;
    }
}
