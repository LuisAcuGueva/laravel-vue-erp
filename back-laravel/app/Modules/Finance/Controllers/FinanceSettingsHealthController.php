<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Services\AccountingSetupHealthService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class FinanceSettingsHealthController extends Controller
{
    use ApiResponse;

    public function __invoke(AccountingSetupHealthService $health): JsonResponse
    {
        return $this->successResponse(data: $health->payload());
    }
}
