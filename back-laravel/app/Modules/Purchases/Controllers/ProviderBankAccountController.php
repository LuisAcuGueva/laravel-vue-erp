<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Purchases\Requests\SyncProviderBankAccountsRequest;
use App\Modules\Purchases\Resources\ProviderBankAccountResource;
use App\Modules\Purchases\Services\ProviderBankAccountService;
use App\Modules\Purchases\Services\ProviderService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProviderBankAccountController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ProviderService $providerService,
        private readonly ProviderBankAccountService $accountService,
    ) {}

    public function sync(string $id, SyncProviderBankAccountsRequest $request): JsonResponse
    {
        $provider = $this->providerService->findById($id);

        if ($provider === null) {
            return $this->errorResponse("Proveedor no encontrado: {$id}", 404);
        }

        return $this->successResponse(
            data: ProviderBankAccountResource::collection($this->accountService->sync($provider, $request->toAccounts())),
        );
    }
}
