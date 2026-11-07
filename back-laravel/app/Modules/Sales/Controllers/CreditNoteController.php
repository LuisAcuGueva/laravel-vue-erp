<?php

declare(strict_types=1);

namespace App\Modules\Sales\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Enums\CreditNoteStatus;
use App\Modules\Sales\Jobs\SendElectronicDocumentJob;
use App\Modules\Sales\Models\CreditNote;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Requests\ListCreditNotesRequest;
use App\Modules\Sales\Requests\StoreCreditNoteRequest;
use App\Modules\Sales\Resources\CreditNoteResource;
use App\Modules\Sales\Services\CreditNoteCancellationService;
use App\Modules\Sales\Services\CreditNoteIssuanceService;
use App\Modules\Users\Models\User;
use App\Support\Branch\ActiveBranch;
use App\Support\Branch\BranchAccessGuard;
use App\Support\Pdf\PdfRenderer;
use App\Support\Pricing\Decimal;
use App\Support\Text\NumberToWords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class CreditNoteController extends Controller
{
    public function __construct(
        private readonly CreditNoteIssuanceService $issuanceService,
        private readonly CreditNoteCancellationService $cancellationService,
        private readonly BranchAccessGuard $branchAccessGuard,
        private readonly PdfRenderer $pdfRenderer,
    ) {}

    /**
     * Listado paginado de notas de crédito con filtros y totales.
     */
    public function index(ListCreditNotesRequest $request, ActiveBranch $activeBranch): AnonymousResourceCollection|JsonResponse
    {
        $query = CreditNote::query()
            ->with(['sale.series', 'client', 'currency', 'documentSeries', 'creator'])
            ->where('branch_id', $activeBranch->id());

        if ($request->filled('sale_id')) {
            $query->where('sale_id', $request->input('sale_id'));
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->input('client_id'));
        }

        if ($request->filled('reason_code')) {
            $query->where('reason_code', $request->input('reason_code'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('issue_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('issue_date', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function (Builder $q) use ($search): void {
                $q->where('full_number', 'ilike', "%{$search}%")
                    ->orWhere('client_name', 'ilike', "%{$search}%")
                    ->orWhere('client_document_number', 'ilike', "%{$search}%")
                    ->orWhereHas('sale', fn (Builder $sq) => $sq->where('full_number', 'ilike', "%{$search}%"));
            });
        }

        // Totales por moneda de las notas filtradas
        $totalsQuery = clone $query;
        /** @var Collection<int, CreditNote> $recordsForTotals */
        $recordsForTotals = $totalsQuery->get(['currency_id', 'total', 'applied_to_balance', 'refunded_amount', 'status']);

        $totalsByCurrency = [];
        foreach ($recordsForTotals as $rec) {
            if ($rec->status === CreditNoteStatus::CANCELLED) {
                continue;
            }

            $cId = $rec->currency_id;
            if (! isset($totalsByCurrency[$cId])) {
                $totalsByCurrency[$cId] = [
                    'currency_id' => $cId,
                    'currency_code' => $rec->currency->code,
                    'currency_symbol' => $rec->currency->symbol,
                    'total' => '0.00',
                    'applied_to_balance' => '0.00',
                    'refunded_amount' => '0.00',
                ];
            }

            $totalsByCurrency[$cId]['total'] = Decimal::add($totalsByCurrency[$cId]['total'], (string) $rec->total, 2);
            $totalsByCurrency[$cId]['applied_to_balance'] = Decimal::add($totalsByCurrency[$cId]['applied_to_balance'], (string) $rec->applied_to_balance, 2);
            $totalsByCurrency[$cId]['refunded_amount'] = Decimal::add($totalsByCurrency[$cId]['refunded_amount'], (string) $rec->refunded_amount, 2);
        }

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $query->orderBy('issue_date', 'desc')->orderBy('number', 'desc')->paginate($perPage);

        return CreditNoteResource::collection($paginator)->additional([
            'meta' => [
                'totals' => array_values($totalsByCurrency),
            ],
        ]);
    }

    /**
     * Resumen de conteos y totales por moneda de las notas de crédito de la sede activa.
     */
    public function summary(ActiveBranch $activeBranch): JsonResponse
    {
        /** @var Collection<int, CreditNote> $records */
        $records = CreditNote::query()
            ->where('branch_id', $activeBranch->id())
            ->with('currency')
            ->get(['id', 'currency_id', 'total', 'status']);

        $totalsByCurrency = [];
        $issuedCount = 0;
        $cancelledCount = 0;

        foreach ($records as $record) {
            if ($record->status === CreditNoteStatus::CANCELLED) {
                $cancelledCount++;

                continue;
            }

            $issuedCount++;

            $currencyId = $record->currency_id;
            if (! isset($totalsByCurrency[$currencyId])) {
                $totalsByCurrency[$currencyId] = [
                    'currency_id' => $currencyId,
                    'currency_code' => $record->currency->code,
                    'currency_symbol' => $record->currency->symbol,
                    'total' => '0.00',
                ];
            }

            $totalsByCurrency[$currencyId]['total'] = Decimal::add($totalsByCurrency[$currencyId]['total'], (string) $record->total, 2);
        }

        return response()->json([
            'data' => [
                'total_count' => $records->count(),
                'issued_count' => $issuedCount,
                'cancelled_count' => $cancelledCount,
                'totals_by_currency' => array_values($totalsByCurrency),
            ],
        ]);
    }

    /**
     * Devuelve información precargada de la venta para emitir una nota de crédito.
     */
    public function prefill(Request $request): JsonResponse
    {
        $saleId = $request->query('sale_id');
        if (! $saleId || ! is_string($saleId)) {
            throw ValidationException::withMessages([
                'sale_id' => 'Debe indicar el parámetro sale_id.',
            ]);
        }

        /** @var Sale|null $sale */
        $sale = Sale::find($saleId);
        if ($sale === null) {
            return response()->json(['message' => 'Venta no encontrada.'], 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $sale->branch_id);

        $data = $this->issuanceService->prefill($saleId);

        return response()->json(['data' => $data]);
    }

    /**
     * Emite una nueva nota de crédito.
     */
    public function store(StoreCreditNoteRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Sale $sale */
        $sale = Sale::whereKey($request->input('sale_id'))->firstOrFail();
        $this->branchAccessGuard->assertAccessible($user, $sale->branch_id);

        $creditNote = $this->issuanceService->issue($request->validated(), $user);
        $creditNote->load(['sale.series', 'items.catalogItem.baseUnit', 'items.itemPresentation', 'documentSeries', 'client', 'currency', 'warehouse', 'creator']);

        return (new CreditNoteResource($creditNote))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Muestra el detalle de una nota de crédito.
     */
    public function show(string $id, Request $request): JsonResponse|CreditNoteResource
    {
        /** @var CreditNote|null $creditNote */
        $creditNote = CreditNote::with([
            'sale.series',
            'items.catalogItem.baseUnit',
            'items.itemPresentation',
            'documentSeries',
            'client',
            'currency',
            'warehouse',
            'creator',
            'canceller',
            'refundPaymentMethod',
            'refundCashSession',
        ])->find($id);

        if ($creditNote === null) {
            return response()->json(['message' => 'Nota de crédito no encontrada.'], 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $creditNote->branch_id);

        return new CreditNoteResource($creditNote);
    }

    /**
     * Genera la representación impresa en PDF de la nota de crédito (A4 o Ticket).
     */
    public function pdf(string $id, Request $request): Response|JsonResponse
    {
        /** @var CreditNote|null $note */
        $note = CreditNote::with([
            'sale.series',
            'items',
            'branch',
            'client',
            'currency',
            'warehouse',
        ])->find($id);

        if ($note === null) {
            return response()->json(['message' => 'Nota de crédito no encontrada.'], 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $note->branch_id);

        $format = $request->query('format', 'a4');
        $inline = $request->boolean('inline', true);
        $amountInWords = NumberToWords::toInvoiceText((string) $note->total, $note->currency, prefix: false);

        $viewData = [
            'note' => $note,
            'amount_in_words' => $amountInWords,
            'watermark' => $note->status === CreditNoteStatus::CANCELLED ? 'ANULADO' : null,
        ];

        if ($format === 'ticket') {
            $lineCount = count($note->items);
            $heightPt = 360 + ($lineCount * 22);

            return $this->pdfRenderer->render('pdf.sales.credit-note-ticket', $viewData, [
                'paper' => [0, 0, 226.77, $heightPt],
                'filename' => "{$note->full_number}.pdf",
                'inline' => $inline,
            ]);
        }

        return $this->pdfRenderer->render('pdf.sales.credit-note-a4', $viewData, [
            'paper' => 'a4',
            'filename' => "{$note->full_number}.pdf",
            'inline' => $inline,
        ]);
    }

    /**
     * Anula una nota de crédito emitida.
     */
    public function cancel(string $id, Request $request): JsonResponse|CreditNoteResource
    {
        /** @var CreditNote|null $note */
        $note = CreditNote::find($id);

        if ($note === null) {
            return response()->json(['message' => 'Nota de crédito no encontrada.'], 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $note->branch_id);

        $reason = (string) ($request->input('reason') ?? $request->input('cancellation_reason') ?? '');
        if (strlen(trim($reason)) < 5) {
            throw ValidationException::withMessages([
                'reason' => 'Debe ingresar un motivo de anulación de al menos 5 caracteres.',
            ]);
        }

        $cancelled = $this->cancellationService->cancel($note, $reason, $user);
        $cancelled->load(['sale.series', 'items', 'documentSeries', 'client', 'currency', 'creator', 'canceller']);

        return new CreditNoteResource($cancelled);
    }

    /**
     * Reintenta el envío de la nota de crédito electrónica al PSE/OSE.
     */
    public function retryEInvoice(string $id, Request $request): JsonResponse
    {
        /** @var CreditNote|null $note */
        $note = CreditNote::find($id);

        if ($note === null) {
            return response()->json(['message' => 'Nota de crédito no encontrada.'], 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $note->branch_id);

        if ($note->status === CreditNoteStatus::CANCELLED) {
            throw ValidationException::withMessages([
                'status' => 'No se puede enviar a SUNAT una nota de crédito anulada.',
            ]);
        }

        if ($note->einvoice_status === 'accepted') {
            throw ValidationException::withMessages([
                'einvoice_status' => 'La nota de crédito ya fue aceptada por SUNAT.',
            ]);
        }

        dispatch(new SendElectronicDocumentJob($note->id, 'credit_note'));

        return response()->json([
            'message' => 'Comprobante encolado para reintento de envío electrónico.',
        ], 202);
    }
}
