<?php

declare(strict_types=1);

namespace App\Modules\Sales\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Enums\EInvoiceStatus;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Jobs\SendElectronicDocumentJob;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Requests\ListSalesRequest;
use App\Modules\Sales\Requests\PrefillSaleRequest;
use App\Modules\Sales\Requests\StoreSaleRequest;
use App\Modules\Sales\Resources\SaleResource;
use App\Modules\Sales\Services\SaleCancellationService;
use App\Modules\Sales\Services\SaleIssuanceService;
use App\Modules\Sales\Services\SaleQueryService;
use App\Modules\Sales\Services\SaleService;
use App\Modules\Users\Models\User;
use App\Support\Branch\ActiveBranch;
use App\Support\Branch\BranchAccessGuard;
use App\Support\Http\ApiResponse;
use App\Support\Pdf\PdfRenderer;
use App\Support\Text\NumberToWords;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SaleController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly SaleIssuanceService $issuanceService,
        private readonly SaleCancellationService $cancellationService,
        private readonly SaleQueryService $queryService,
        private readonly SaleService $saleService,
        private readonly BranchAccessGuard $branchAccessGuard,
        private readonly PdfRenderer $pdfRenderer,
    ) {}

    public function index(ListSalesRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $activeBranch = app(ActiveBranch::class)->get();

        $filters = $request->validated();
        $paginated = $this->queryService->paginate(
            $filters,
            $user,
            $activeBranch->id,
            (int) $request->query('per_page', 15)
        );

        $totals = $this->queryService->totals($filters, $user, $activeBranch->id);

        return SaleResource::collection($paginated)->additional([
            'meta' => [
                'totals' => $totals,
            ],
        ]);
    }

    public function store(StoreSaleRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $activeBranch = app(ActiveBranch::class)->get();
        $sale = $this->issuanceService->issue($request->validated(), $user, $activeBranch);

        return (new SaleResource($sale))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id, Request $request): JsonResponse|SaleResource
    {
        $sale = $this->saleService->findById($id);

        if ($sale === null) {
            return $this->errorResponse("Venta no encontrada: {$id}", 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $sale->branch_id);

        return new SaleResource($sale);
    }

    public function prefill(PrefillSaleRequest $request): JsonResponse
    {
        $activeBranch = app(ActiveBranch::class)->get();
        $quotationId = (string) $request->validated('quotation_id');

        $data = $this->saleService->prefillFromQuotation($quotationId, $activeBranch->id);

        return response()->json(['data' => $data]);
    }

    public function cancel(string $id, Request $request): JsonResponse|SaleResource
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $sale = Sale::find($id);
        if ($sale === null) {
            return $this->errorResponse("Venta no encontrada: {$id}", 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $sale->branch_id);

        $cancelled = $this->cancellationService->cancel($sale, (string) $request->input('reason'), $user);

        return new SaleResource($cancelled);
    }

    public function retryEInvoice(string $id, Request $request): JsonResponse
    {
        $sale = Sale::find($id);
        if ($sale === null) {
            return $this->errorResponse("Venta no encontrada: {$id}", 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $sale->branch_id);

        if ($sale->einvoice_attempts >= 5) {
            return $this->errorResponse('Se alcanzó el límite máximo de 5 reintentos de envío para este comprobante.', 422);
        }

        if (! in_array($sale->einvoice_status, [EInvoiceStatus::Error, EInvoiceStatus::Rejected], true)) {
            return $this->errorResponse('Solo se pueden reintentar comprobantes en estado de error o rechazados.', 422);
        }

        SendElectronicDocumentJob::dispatch($sale->id);

        return response()->json(['message' => 'Envío de comprobante encolado correctamente.'], 202);
    }

    public function pdf(string $id, Request $request): Response|JsonResponse
    {
        $sale = $this->saleService->findById($id);
        if ($sale === null) {
            return $this->errorResponse("Venta no encontrada: {$id}", 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $sale->branch_id);

        $format = $request->query('format', 'a4');
        $inline = $request->boolean('inline', true);
        $amountInWords = NumberToWords::toInvoiceText($sale->total, $sale->currency, prefix: false);

        $viewData = [
            'sale' => $sale,
            'amount_in_words' => $amountInWords,
            'watermark' => $sale->status === SaleStatus::Cancelled ? 'ANULADO' : null,
        ];

        if ($format === 'ticket') {
            $lineCount = count($sale->items);
            $paymentCount = count($sale->payments);
            $heightPt = 380 + ($lineCount * 22) + ($paymentCount * 18);

            return $this->pdfRenderer->render('pdf.sales.voucher-ticket', $viewData, [
                'paper' => [0, 0, 226.77, $heightPt],
                'filename' => "{$sale->full_number}.pdf",
                'inline' => $inline,
            ]);
        }

        return $this->pdfRenderer->render('pdf.sales.voucher-a4', $viewData, [
            'paper' => 'a4',
            'filename' => "{$sale->full_number}.pdf",
            'inline' => $inline,
        ]);
    }
}
