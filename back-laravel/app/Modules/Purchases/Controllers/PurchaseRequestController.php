<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Purchases\Models\PurchaseRequest;
use App\Modules\Purchases\Requests\ListPurchaseRequestsRequest;
use App\Modules\Purchases\Requests\LowStockRequestRequest;
use App\Modules\Purchases\Requests\RejectPurchaseDocumentRequest;
use App\Modules\Purchases\Requests\StorePurchaseRequestRequest;
use App\Modules\Purchases\Resources\PurchaseRequestItemResource;
use App\Modules\Purchases\Resources\PurchaseRequestResource;
use App\Modules\Purchases\Services\PurchaseRequestService;
use App\Modules\Users\Models\User;
use App\Support\Branch\ActiveBranch;
use App\Support\Branch\BranchAccessGuard;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseRequestController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PurchaseRequestService $service,
        private readonly BranchAccessGuard $branchAccessGuard,
    ) {}

    public function index(ListPurchaseRequestsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $paginator = $this->service
            ->query($request->validated(), $user, app(ActiveBranch::class)->id())
            ->paginate((int) $request->input('per_page', 15));

        return $this->successResponse(
            data: PurchaseRequestResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function pendingItems(Request $request): JsonResponse
    {
        $data = $request->validate(['warehouse_id' => ['required', 'uuid']]);

        return $this->successResponse(
            data: PurchaseRequestItemResource::collection($this->service->pendingItems($data['warehouse_id'])),
        );
    }

    public function store(StorePurchaseRequestRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $branch = app(ActiveBranch::class)->get();

        $created = $this->service->create($request->toAttributes(), $user, $branch);

        return (new PurchaseRequestResource($created))->response()->setStatusCode(201);
    }

    public function fromLowStock(LowStockRequestRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var array{warehouse_id: string, area_id: string, category_id?: string|null} $data */
        $data = $request->validated();

        $created = $this->service->fromLowStock(
            $data['warehouse_id'],
            $data['area_id'],
            $data['category_id'] ?? null,
            $user,
            app(ActiveBranch::class)->get(),
        );

        return (new PurchaseRequestResource($created))->response()->setStatusCode(201);
    }

    public function show(string $id, Request $request): JsonResponse|PurchaseRequestResource
    {
        $pr = $this->resolve($id, $request);

        return new PurchaseRequestResource($pr);
    }

    public function update(string $id, StorePurchaseRequestRequest $request): JsonResponse|PurchaseRequestResource
    {
        /** @var User $user */
        $user = $request->user();

        return new PurchaseRequestResource($this->service->update($this->resolve($id, $request), $request->toAttributes(), $user));
    }

    public function submit(string $id, Request $request): JsonResponse|PurchaseRequestResource
    {
        /** @var User $user */
        $user = $request->user();

        return new PurchaseRequestResource($this->service->submit($this->resolve($id, $request), $user));
    }

    public function approve(string $id, Request $request): JsonResponse|PurchaseRequestResource
    {
        /** @var User $user */
        $user = $request->user();

        return new PurchaseRequestResource($this->service->approve($this->resolve($id, $request), $user));
    }

    public function reject(string $id, RejectPurchaseDocumentRequest $request): JsonResponse|PurchaseRequestResource
    {
        /** @var User $user */
        $user = $request->user();

        return new PurchaseRequestResource($this->service->reject($this->resolve($id, $request), $request->reason(), $user));
    }

    public function cancel(string $id, RejectPurchaseDocumentRequest $request): JsonResponse|PurchaseRequestResource
    {
        /** @var User $user */
        $user = $request->user();

        return new PurchaseRequestResource($this->service->cancel($this->resolve($id, $request), $request->reason(), $user));
    }

    /**
     * Carga el requerimiento y exige que su sede sea accesible para el usuario (404 si no).
     */
    private function resolve(string $id, Request $request): PurchaseRequest
    {
        $pr = $this->service->findById($id);

        if ($pr === null) {
            abort(404, "Requerimiento no encontrado: {$id}");
        }

        /** @var User $user */
        $user = $request->user();
        $this->branchAccessGuard->assertAccessible($user, $pr->branch_id);

        return $pr;
    }
}
