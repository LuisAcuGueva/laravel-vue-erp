<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\BankOperation;
use App\Modules\Finance\Requests\StoreBankOperationRequest;
use App\Modules\Finance\Resources\BankOperationResource;
use App\Modules\Finance\Services\BankOperationService;
use App\Modules\Users\Models\User;
use App\Support\Branch\BranchAccessGuard;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankOperationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly BankOperationService $operationService,
        private readonly BranchAccessGuard $branchAccessGuard,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'type' => $request->query('type'),
            'status' => $request->query('status'),
            'bank_account_id' => $request->query('bank_account_id'),
            'search' => $request->query('search'),
        ];
        $perPage = (int) $request->query('per_page', 15);

        $paginator = $this->operationService->paginate($filters, $perPage);

        return $this->successResponse(
            data: BankOperationResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(StoreBankOperationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $voucher = $request->hasFile('voucher') ? $request->file('voucher') : null;
        $operation = $this->operationService->create($request->validated(), $user, $voucher);

        return $this->createdResponse(
            data: new BankOperationResource($this->operationService->find($operation->id)),
            message: 'Operación bancaria registrada correctamente.',
        );
    }

    public function show(string $id): JsonResponse
    {
        $operation = $this->operationService->find($id);

        return $this->successResponse(
            data: new BankOperationResource($operation),
        );
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();

        /** @var BankOperation $operation */
        $operation = BankOperation::with('bankAccount')->findOrFail($id);

        if ($operation->bankAccount->branch_id !== null) {
            $this->branchAccessGuard->assertAccessible($user, $operation->bankAccount->branch_id);
        }

        $cancelled = $this->operationService->cancel($operation, (string) $validated['reason'], $user);

        return $this->successResponse(
            data: new BankOperationResource($this->operationService->find($cancelled->id)),
            message: 'Operación bancaria anulada correctamente.',
        );
    }

    public function voucher(Request $request, string $id): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var BankOperation $operation */
        $operation = BankOperation::with('bankAccount')->findOrFail($id);

        if ($operation->bankAccount->branch_id !== null) {
            $this->branchAccessGuard->assertAccessible($user, $operation->bankAccount->branch_id);
        }

        if ($operation->voucher_path === null || ! Storage::disk('local')->exists($operation->voucher_path)) {
            abort(404, 'Comprobante no encontrado.');
        }

        return Storage::disk('local')->download($operation->voucher_path);
    }
}
