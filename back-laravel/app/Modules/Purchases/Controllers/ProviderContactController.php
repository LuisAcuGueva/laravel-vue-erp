<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Purchases\Requests\SyncProviderContactsRequest;
use App\Modules\Purchases\Resources\ProviderContactResource;
use App\Modules\Purchases\Services\ProviderContactService;
use App\Modules\Purchases\Services\ProviderService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProviderContactController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ProviderService $providerService,
        private readonly ProviderContactService $contactService,
    ) {}

    public function sync(string $id, SyncProviderContactsRequest $request): JsonResponse
    {
        $provider = $this->providerService->findById($id);

        if ($provider === null) {
            return $this->errorResponse("Proveedor no encontrado: {$id}", 404);
        }

        return $this->successResponse(
            data: ProviderContactResource::collection($this->contactService->sync($provider, $request->toContacts())),
        );
    }
}
