<?php

declare(strict_types=1);

namespace App\Modules\Sales\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Exports\ReceivableInstallmentCsvExport;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Requests\RescheduleInstallmentsRequest;
use App\Modules\Sales\Resources\ReceivableInstallmentResource;
use App\Modules\Sales\Services\InstallmentScheduleService;
use App\Modules\Sales\Services\ReceivableQueryService;
use App\Modules\Users\Models\User;
use App\Support\Branch\ActiveBranch;
use App\Support\Branch\BranchAccessGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReceivableController extends Controller
{
    public function __construct(
        private readonly ReceivableQueryService $queryService,
        private readonly InstallmentScheduleService $scheduleService,
        private readonly ActiveBranch $activeBranch,
        private readonly BranchAccessGuard $branchAccessGuard,
    ) {}

    /**
     * Listado paginado de cuotas por cobrar con resumen por moneda.
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
            'overdue',
            'due_from',
            'due_to',
            'search',
        ]);
        $filters['branch_id'] = $branch->id;

        $perPage = (int) $request->input('per_page', 25);
        $paginator = $this->queryService->openInstallments($filters, $perPage);
        $summary = $this->queryService->openInstallmentsSummary($filters);

        return response()->json([
            'data' => ReceivableInstallmentResource::collection($paginator->items()),
            'summary' => $summary,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Exportación CSV de cuotas por cobrar.
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
            'overdue',
            'due_from',
            'due_to',
            'search',
        ]);
        $filters['branch_id'] = $branch->id;

        $query = $this->queryService->openInstallmentsQuery($filters);

        return (new ReceivableInstallmentCsvExport)->download($query, 'cuotas-por-cobrar');
    }

    /**
     * Reporte de antigüedad de deuda (Aging).
     */
    public function aging(Request $request): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $branch = $this->activeBranch->get();
        $this->branchAccessGuard->assertAccessible($user, $branch->id);

        $asOf = $request->input('as_of');
        $report = $this->queryService->aging($branch->id, $asOf ? (string) $asOf : null);

        return response()->json($report);
    }

    /**
     * Reprograma el cronograma de cuotas de una venta.
     */
    public function reschedule(RescheduleInstallmentsRequest $request, string $id): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        /** @var Sale $sale */
        $sale = Sale::whereKey($id)->firstOrFail();
        $this->branchAccessGuard->assertAccessible($user, $sale->branch_id);

        /** @var array<int, array{due_date: string, amount: numeric-string|float|int}> $installmentsInput */
        $installmentsInput = $request->validated('installments');
        $this->scheduleService->reschedule($sale, $installmentsInput, $user);

        $sale->load([
            'installments.allocations.collection',
            'collectionAllocations.collection',
        ]);

        return response()->json([
            'message' => 'Cronograma de cuotas reprogramado correctamente.',
            'installments' => ReceivableInstallmentResource::collection($sale->installments),
        ]);
    }
}
