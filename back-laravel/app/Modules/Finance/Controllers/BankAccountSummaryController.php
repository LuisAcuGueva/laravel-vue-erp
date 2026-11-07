<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Services\BankQueryService;
use App\Modules\Maintenance\Services\UserBranchService;
use App\Modules\Users\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankAccountSummaryController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly BankQueryService $queryService,
        private readonly UserBranchService $userBranchService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $accessibleBranches = $this->userBranchService->accessibleBranches($user);
        $accessibleBranchIds = $accessibleBranches->pluck('id')->all();

        $accounts = BankAccount::query()
            ->active()
            ->where(function ($q) use ($accessibleBranchIds) {
                $q->whereNull('branch_id')->orWhereIn('branch_id', $accessibleBranchIds);
            })
            ->with(['bank', 'currency', 'openPeriod'])
            ->orderBy('alias')
            ->get();

        $summary = $this->queryService->summary($accounts);

        return $this->successResponse(
            data: $summary,
        );
    }
}
