<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\BankPeriod;
use App\Modules\Finance\Requests\CloseBankPeriodRequest;
use App\Modules\Finance\Requests\OpenBankPeriodRequest;
use App\Modules\Finance\Resources\BankPeriodResource;
use App\Modules\Finance\Services\BankPeriodService;
use App\Modules\Users\Models\User;
use App\Support\Branch\BranchAccessGuard;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankPeriodController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly BankPeriodService $periodService,
        private readonly BranchAccessGuard $branchAccessGuard,
    ) {}

    public function index(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var BankAccount $account */
        $account = BankAccount::findOrFail($id);

        if ($account->branch_id !== null) {
            $this->branchAccessGuard->assertAccessible($user, $account->branch_id);
        }

        $periods = $account->periods()
            ->with(['closedByUser'])
            ->orderByDesc('number')
            ->get();

        return $this->successResponse(
            data: BankPeriodResource::collection($periods),
        );
    }

    public function openInitial(OpenBankPeriodRequest $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var BankAccount $account */
        $account = BankAccount::findOrFail($id);

        if ($account->branch_id !== null) {
            $this->branchAccessGuard->assertAccessible($user, $account->branch_id);
        }

        $period = $this->periodService->openInitial(
            account: $account,
            openingBalance: (string) $request->input('opening_balance'),
            openedAt: (string) $request->input('opened_at'),
            user: $user,
        );

        return $this->createdResponse(
            data: new BankPeriodResource($period),
            message: 'Cuenta bancaria abierta correctamente.',
        );
    }

    public function close(CloseBankPeriodRequest $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var BankPeriod $period */
        $period = BankPeriod::with('bankAccount')->findOrFail($id);

        if ($period->bankAccount->branch_id !== null) {
            $this->branchAccessGuard->assertAccessible($user, $period->bankAccount->branch_id);
        }

        $result = $this->periodService->close(
            period: $period,
            statementBalance: (string) $request->input('statement_balance'),
            closedAt: (string) $request->input('closed_at'),
            notes: $request->input('notes') ? (string) $request->input('notes') : null,
            user: $user,
        );

        return $this->successResponse(
            data: [
                'closed' => new BankPeriodResource($result['closed']),
                'next' => new BankPeriodResource($result['next']),
            ],
            message: 'Periodo bancario cerrado correctamente.',
        );
    }
}
