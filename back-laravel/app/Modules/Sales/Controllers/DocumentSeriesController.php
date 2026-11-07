<?php

declare(strict_types=1);

namespace App\Modules\Sales\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Models\DocumentSeries;
use App\Modules\Sales\Requests\DocumentSeriesRequest;
use App\Modules\Sales\Resources\DocumentSeriesResource;
use App\Modules\Sales\Services\DocumentSeriesService;
use App\Modules\Users\Models\User;
use App\Support\Branch\ActiveBranch;
use App\Support\Branch\BranchAccessGuard;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DocumentSeriesController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly DocumentSeriesService $seriesService,
        private readonly BranchAccessGuard $branchAccessGuard,
    ) {}

    public function available(Request $request): AnonymousResourceCollection
    {
        $activeBranch = app(ActiveBranch::class)->get();
        $boxId = $request->query('box_id');
        $documentType = $request->query('document_type');

        $series = $this->seriesService->available(
            $activeBranch,
            is_string($boxId) ? $boxId : null,
            is_string($documentType) ? $documentType : null
        );

        return DocumentSeriesResource::collection($series);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $activeBranch = app(ActiveBranch::class)->get();

        $branchId = (string) $request->query('branch_id', $activeBranch->id);
        $this->branchAccessGuard->assertAccessible($user, $branchId);

        $filters = $request->only(['document_type', 'is_active', 'search', 'box_id']);
        $filters['branch_id'] = $branchId;

        $paginated = $this->seriesService->paginate($filters, (int) $request->query('per_page', 15));

        return DocumentSeriesResource::collection($paginated);
    }

    public function store(DocumentSeriesRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, (string) $request->input('branch_id'));

        $series = $this->seriesService->create($request->validated());

        return (new DocumentSeriesResource($series))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id, Request $request): JsonResponse|DocumentSeriesResource
    {
        $series = DocumentSeries::find($id);
        if ($series === null) {
            return $this->errorResponse("Serie no encontrada: {$id}", 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $series->branch_id);

        return new DocumentSeriesResource($series);
    }

    public function update(string $id, DocumentSeriesRequest $request): JsonResponse|DocumentSeriesResource
    {
        $series = DocumentSeries::find($id);
        if ($series === null) {
            return $this->errorResponse("Serie no encontrada: {$id}", 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $series->branch_id);

        $updated = $this->seriesService->update($series, $request->validated());

        return new DocumentSeriesResource($updated);
    }

    public function destroy(string $id, Request $request): Response|JsonResponse
    {
        $series = DocumentSeries::find($id);
        if ($series === null) {
            return $this->errorResponse("Serie no encontrada: {$id}", 404);
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $series->branch_id);

        $this->seriesService->delete($series);

        return response()->noContent();
    }
}
