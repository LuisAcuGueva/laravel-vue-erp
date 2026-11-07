<?php

declare(strict_types=1);

namespace App\Modules\Sales\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Exports\CollectionCsvExport;
use App\Modules\Sales\Models\Collection;
use App\Modules\Sales\Requests\CancelCollectionRequest;
use App\Modules\Sales\Requests\StoreCollectionRequest;
use App\Modules\Sales\Resources\CollectionResource;
use App\Modules\Sales\Services\CollectionPdfBuilder;
use App\Modules\Sales\Services\CollectionService;
use App\Modules\Sales\Services\ReceivableQueryService;
use App\Modules\Users\Models\User;
use App\Support\Branch\ActiveBranch;
use App\Support\Branch\BranchAccessGuard;
use App\Support\Pdf\PdfRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CollectionController extends Controller
{
    public function __construct(
        private readonly CollectionService $collectionService,
        private readonly ReceivableQueryService $queryService,
        private readonly ActiveBranch $activeBranch,
        private readonly BranchAccessGuard $branchAccessGuard,
        private readonly PdfRenderer $pdfRenderer,
        private readonly CollectionPdfBuilder $pdfBuilder,
    ) {}

    /**
     * Listado paginado de cobranzas.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $branch = $this->activeBranch->get();
        $this->branchAccessGuard->assertAccessible($user, $branch->id);

        $filters = $request->only([
            'client_id',
            'currency_id',
            'status',
            'from',
            'to',
            'search',
        ]);
        $filters['branch_id'] = $branch->id;

        $perPage = (int) $request->input('per_page', 25);
        $paginator = $this->queryService->collections($filters, $perPage);

        return response()->json([
            'data' => CollectionResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Exportación CSV de cobranzas.
     */
    public function export(Request $request): Response
    {
        $user = $request->user();
        assert($user instanceof User);

        $branch = $this->activeBranch->get();
        $this->branchAccessGuard->assertAccessible($user, $branch->id);

        $filters = $request->only([
            'client_id',
            'currency_id',
            'status',
            'from',
            'to',
            'search',
        ]);
        $filters['branch_id'] = $branch->id;

        $query = $this->queryService->collectionsQuery($filters);

        return (new CollectionCsvExport)->download($query, 'cobranzas');
    }

    /**
     * Detalle de una cobranza.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        /** @var Collection $collection */
        $collection = Collection::with([
            'client',
            'currency',
            'paymentMethod',
            'cashSession.box',
            'bankAccount',
            'branch',
            'creator',
            'allocations.installment',
            'allocations.sale',
        ])->whereKey($id)->firstOrFail();

        $this->branchAccessGuard->assertAccessible($user, $collection->branch_id);

        return response()->json([
            'data' => new CollectionResource($collection),
        ]);
    }

    /**
     * Registra una cobranza y aplica pagos.
     */
    public function store(StoreCollectionRequest $request): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $branch = $this->activeBranch->get();
        $collection = $this->collectionService->create($request->validated(), $user, $branch);

        return response()->json([
            'message' => 'Cobranza registrada correctamente.',
            'data' => new CollectionResource($collection),
        ], Response::HTTP_CREATED);
    }

    /**
     * Anula una cobranza.
     */
    public function cancel(CancelCollectionRequest $request, string $id): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        /** @var Collection $collection */
        $collection = Collection::whereKey($id)->firstOrFail();
        $this->branchAccessGuard->assertAccessible($user, $collection->branch_id);

        $cancelled = $this->collectionService->cancel($collection, $request->validated('reason'), $user);

        return response()->json([
            'message' => 'Cobranza anulada correctamente.',
            'data' => new CollectionResource($cancelled),
        ]);
    }

    /**
     * Genera el recibo de cobranza en PDF.
     */
    public function pdf(Request $request, string $id): Response
    {
        $user = $request->user();
        assert($user instanceof User);

        /** @var Collection $collection */
        $collection = Collection::whereKey($id)->firstOrFail();
        $this->branchAccessGuard->assertAccessible($user, $collection->branch_id);

        $data = $this->pdfBuilder->build($collection);

        return $this->pdfRenderer->render('pdf.sales.collection-receipt', $data, [
            'filename' => "recibo-cobranza-{$collection->number}.pdf",
        ]);
    }
}
