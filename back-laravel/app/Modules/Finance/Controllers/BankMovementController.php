<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Exceptions\BankLedgerException;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\BankMovement;
use App\Modules\Finance\Requests\ListBankMovementsRequest;
use App\Modules\Finance\Requests\StoreBankMovementRequest;
use App\Modules\Finance\Resources\BankMovementResource;
use App\Modules\Finance\Services\BankLedgerService;
use App\Modules\Finance\Services\BankQueryService;
use App\Modules\Users\Models\User;
use App\Support\Branch\ActiveBranch;
use App\Support\Branch\BranchAccessGuard;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankMovementController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly BankLedgerService $ledgerService,
        private readonly BankQueryService $queryService,
        private readonly BranchAccessGuard $branchAccessGuard,
    ) {}

    public function index(ListBankMovementsRequest $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var BankAccount $account */
        $account = BankAccount::findOrFail($id);

        if ($account->branch_id !== null) {
            $this->branchAccessGuard->assertAccessible($user, $account->branch_id);
        }

        $dateFrom = $request->query('date_from') ? (string) $request->query('date_from') : null;
        $dateTo = $request->query('date_to') ? (string) $request->query('date_to') : null;
        $filters = [
            'direction' => $request->query('direction'),
            'concept_id' => $request->query('concept_id'),
            'search' => $request->query('search'),
        ];
        $perPage = (int) $request->query('per_page', 15);

        $result = $this->queryService->movements($account, $dateFrom, $dateTo, $filters, $perPage);
        $paginator = $result['movements'];

        return $this->successResponse(
            data: [
                'opening' => $result['opening'],
                'movements' => BankMovementResource::collection($paginator->items()),
                'closing' => $result['closing'],
            ],
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(StoreBankMovementRequest $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var BankAccount $account */
        $account = BankAccount::findOrFail($id);

        if ($account->branch_id !== null) {
            $this->branchAccessGuard->assertAccessible($user, $account->branch_id);
        }

        $voucherPath = null;
        if ($request->hasFile('voucher')) {
            $file = $request->file('voucher');
            if ($file !== null) {
                $stored = $file->store('bank-vouchers', 'local');
                if (is_string($stored)) {
                    $voucherPath = $stored;
                }
            }
        }

        $activeBranch = app(ActiveBranch::class)->get();
        $branchId = $activeBranch->id ?? $account->branch_id;

        $movement = DB::transaction(function () use ($request, $id, $voucherPath, $branchId) {
            $movementData = $request->toMovementData($id, $voucherPath, $branchId);

            return $this->ledgerService->register($movementData);
        });

        return $this->createdResponse(
            data: new BankMovementResource($movement->load(['concept', 'paymentMethod', 'creator'])),
            message: 'Movimiento bancario registrado correctamente.',
        );
    }

    public function voucher(Request $request, string $id): StreamedResponse
    {
        /** @var BankMovement $movement */
        $movement = BankMovement::findOrFail($id);

        if ($movement->branch_id !== null) {
            /** @var User $user */
            $user = $request->user();
            $this->branchAccessGuard->assertAccessible($user, $movement->branch_id);
        }

        if ($movement->voucher_path === null || ! Storage::disk('local')->exists($movement->voucher_path)) {
            abort(404, 'Comprobante no encontrado.');
        }

        return Storage::disk('local')->download($movement->voucher_path);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();

        /** @var BankMovement $movement */
        $movement = BankMovement::findOrFail($id);

        if ($movement->branch_id !== null) {
            $this->branchAccessGuard->assertAccessible($user, $movement->branch_id);
        }

        if (! $movement->is_manual) {
            throw new BankLedgerException('Solo se pueden anular movimientos bancarios manuales por esta vía.', 422);
        }

        $reversal = DB::transaction(function () use ($movement, $validated, $user) {
            return $this->ledgerService->reverse(
                movement: $movement,
                source: $movement,
                sourceNumber: $movement->operation_number ?? $movement->id,
                reason: (string) $validated['reason'],
                createdBy: $user->id,
            );
        });

        return $this->successResponse(
            data: new BankMovementResource($reversal->load(['concept', 'paymentMethod', 'creator'])),
            message: 'Movimiento bancario anulado correctamente.',
        );
    }
}
