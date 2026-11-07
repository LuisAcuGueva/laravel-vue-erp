<?php

declare(strict_types=1);

namespace App\Modules\Sales\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Models\Quotation;
use App\Modules\Sales\Requests\ListQuotationsRequest;
use App\Modules\Sales\Requests\StoreQuotationRequest;
use App\Modules\Sales\Resources\QuotationResource;
use App\Modules\Sales\Services\QuotationPdfBuilder;
use App\Modules\Sales\Services\QuotationService;
use App\Modules\Sales\Services\QuotationWorkflowService;
use App\Modules\Users\Models\User;
use App\Support\Branch\ActiveBranch;
use App\Support\Branch\BranchAccessGuard;
use App\Support\Pdf\PdfRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class QuotationController extends Controller
{
    public function __construct(
        private readonly QuotationService $quotationService,
        private readonly QuotationWorkflowService $workflowService,
        private readonly BranchAccessGuard $branchAccessGuard,
    ) {}

    public function index(ListQuotationsRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $branch = app(ActiveBranch::class)->get();

        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 15);

        $paginator = $this->quotationService
            ->query($filters, $user, $branch)
            ->paginate($perPage);

        $totals = $this->quotationService->totalsByStatus($user, $branch);

        return QuotationResource::collection($paginator)->additional([
            'meta' => [
                'totals_by_status' => $totals,
            ],
        ]);
    }

    public function store(StoreQuotationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $branch = app(ActiveBranch::class)->get();

        $quotation = $this->quotationService->create($request->validated(), $user, $branch);

        return (new QuotationResource($quotation))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id, Request $request): QuotationResource
    {
        $quotation = $this->resolve($id, $request);

        return new QuotationResource($quotation);
    }

    public function update(StoreQuotationRequest $request, string $id): QuotationResource
    {
        $quotation = $this->resolve($id, $request);

        /** @var User $user */
        $user = $request->user();

        $updated = $this->quotationService->update($quotation, $request->validated(), $user);

        return new QuotationResource($updated);
    }

    public function availability(string $id, Request $request): JsonResponse
    {
        $quotation = $this->resolve($id, $request);
        $branch = $quotation->branch;

        $availability = $this->quotationService->availability($quotation, $branch);

        return response()->json([
            'data' => $availability,
        ]);
    }

    public function pdf(
        string $id,
        Request $request,
        PdfRenderer $renderer,
        QuotationPdfBuilder $builder
    ): Response {
        $quotation = $this->resolve($id, $request);

        return $renderer->render('pdf.sales.quotation', $builder->build($quotation), [
            'filename' => $quotation->number.'.pdf',
        ]);
    }

    public function duplicate(string $id, Request $request): JsonResponse
    {
        $quotation = $this->resolve($id, $request);

        /** @var User $user */
        $user = $request->user();
        $branch = app(ActiveBranch::class)->get();

        $result = $this->quotationService->duplicate($quotation, $user, $branch);

        return (new QuotationResource($result['quotation']))
            ->additional([
                'meta' => [
                    'skipped_lines' => $result['skipped_lines'],
                ],
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function send(string $id, Request $request): QuotationResource
    {
        $quotation = $this->resolve($id, $request);

        /** @var User $user */
        $user = $request->user();

        $sent = $this->workflowService->send($quotation, $user);

        return new QuotationResource($sent);
    }

    public function accept(string $id, Request $request): QuotationResource
    {
        $quotation = $this->resolve($id, $request);

        /** @var User $user */
        $user = $request->user();

        $accepted = $this->workflowService->accept($quotation, $user);

        return new QuotationResource($accepted);
    }

    public function reject(string $id, Request $request): QuotationResource
    {
        $quotation = $this->resolve($id, $request);

        /** @var User $user */
        $user = $request->user();
        $reason = $request->input('reason');

        $rejected = $this->workflowService->reject($quotation, $user, is_string($reason) ? $reason : null);

        return new QuotationResource($rejected);
    }

    public function cancel(string $id, Request $request): QuotationResource
    {
        $quotation = $this->resolve($id, $request);

        /** @var User $user */
        $user = $request->user();
        $reason = $request->input('reason');

        $cancelled = $this->workflowService->cancel($quotation, $user, is_string($reason) ? $reason : null);

        return new QuotationResource($cancelled);
    }

    private function resolve(string $id, Request $request): Quotation
    {
        /** @var Quotation $quotation */
        $quotation = Quotation::with([
            'items.catalogItem',
            'items.presentation.unit',
            'client',
            'contact',
            'seller',
            'currency',
            'createdBy',
            'discountOverrideBy',
            'rejectedBy',
            'cancelledBy',
            'branch',
        ])->findOrFail($id);

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $quotation->branch_id);

        return $quotation;
    }
}
