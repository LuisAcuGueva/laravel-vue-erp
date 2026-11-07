<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Purchases\Models\SupplierInvoice;
use App\Modules\Purchases\Requests\CancelSupplierDocumentRequest;
use App\Modules\Purchases\Requests\ListSupplierInvoicesRequest;
use App\Modules\Purchases\Requests\PrefillSupplierInvoiceRequest;
use App\Modules\Purchases\Requests\StoreSupplierInvoiceRequest;
use App\Modules\Purchases\Resources\SupplierInvoiceResource;
use App\Modules\Purchases\Services\SupplierInvoiceService;
use App\Modules\Users\Models\User;
use App\Support\Branch\ActiveBranch;
use App\Support\Branch\BranchAccessGuard;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierInvoiceController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly SupplierInvoiceService $service,
        private readonly BranchAccessGuard $branchAccessGuard,
    ) {}

    public function index(ListSupplierInvoicesRequest $request): JsonResponse
    {
        $paginator = $this->service
            ->query($request->validated(), app(ActiveBranch::class)->id())
            ->paginate((int) $request->input('per_page', 15));

        return $this->successResponse(
            data: SupplierInvoiceResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function prefill(PrefillSupplierInvoiceRequest $request): JsonResponse
    {
        /** @var list<string> $receiptIds */
        $receiptIds = array_values((array) $request->validated('receipt_ids'));

        return $this->successResponse(
            data: $this->service->prefill($receiptIds, app(ActiveBranch::class)->id()),
        );
    }

    public function store(StoreSupplierInvoiceRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $result = $this->service->create($request->toAttributes(), $user, app(ActiveBranch::class)->get(), $request->attachment());

        return $this->createdResponse(
            data: new SupplierInvoiceResource($result['invoice']),
            message: 'Factura de proveedor registrada correctamente.',
            meta: $result['warnings'] !== [] ? ['warnings' => $result['warnings']] : [],
        );
    }

    public function show(string $id, Request $request): JsonResponse
    {
        return $this->successResponse(data: new SupplierInvoiceResource($this->resolve($id, $request)));
    }

    public function attachment(string $id, Request $request): StreamedResponse
    {
        $invoice = $this->resolve($id, $request);

        if ($invoice->attachment_path === null || ! Storage::disk('local')->exists($invoice->attachment_path)) {
            abort(404, 'Adjunto no encontrado.');
        }

        return Storage::disk('local')->download($invoice->attachment_path);
    }

    public function cancel(string $id, CancelSupplierDocumentRequest $request): JsonResponse
    {
        $invoice = $this->resolve($id, $request);

        /** @var User $user */
        $user = $request->user();

        $cancelled = $this->service->cancel($invoice, (string) $request->validated('reason'), $user);

        return $this->successResponse(
            data: new SupplierInvoiceResource($cancelled),
            message: 'Factura de proveedor anulada correctamente.',
        );
    }

    private function resolve(string $id, Request $request): SupplierInvoice
    {
        $invoice = $this->service->findById($id);

        if ($invoice === null) {
            abort(404, "Factura de proveedor no encontrada: {$id}");
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $invoice->branch_id);

        return $invoice;
    }
}
