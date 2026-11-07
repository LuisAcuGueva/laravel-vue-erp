<?php

declare(strict_types=1);

namespace App\Modules\Clients\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clients\Requests\SyncClientContactsRequest;
use App\Modules\Clients\Resources\ClientContactResource;
use App\Modules\Clients\Services\ClientContactService;
use App\Modules\Clients\Services\ClientService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class ClientContactController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ClientService $clientService,
        private readonly ClientContactService $contactService,
    ) {}

    public function sync(string $id, SyncClientContactsRequest $request): JsonResponse
    {
        $client = $this->clientService->findById($id);

        if ($client === null) {
            return $this->errorResponse("Cliente no encontrado: {$id}", 404);
        }

        return $this->successResponse(
            data: ClientContactResource::collection($this->contactService->sync($client, $request->toContacts())),
        );
    }
}
