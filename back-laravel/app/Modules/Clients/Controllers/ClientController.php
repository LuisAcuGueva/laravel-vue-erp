<?php

declare(strict_types=1);

namespace App\Modules\Clients\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clients\Exports\ClientCsvExport;
use App\Modules\Clients\Requests\ClientRequest;
use App\Modules\Clients\Requests\ListClientsRequest;
use App\Modules\Clients\Requests\UpdateClientCreditRequest;
use App\Modules\Clients\Resources\ClientOptionResource;
use App\Modules\Clients\Resources\ClientResource;
use App\Modules\Clients\Services\ClientService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ClientService $clientService,
    ) {}

    public function index(ListClientsRequest $request): JsonResponse
    {
        $paginator = $this->clientService->list($request->validated());

        return $this->successResponse(
            data: ClientResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function options(Request $request): JsonResponse
    {
        $options = $this->clientService->options($request->query('search'));

        return $this->successResponse(
            data: ClientOptionResource::collection($options),
        );
    }

    public function export(ListClientsRequest $request): StreamedResponse
    {
        return app(ClientCsvExport::class)->download(
            $this->clientService->query($request->validated()),
            'clientes',
        );
    }

    public function store(ClientRequest $request): JsonResponse
    {
        $client = $this->clientService->create($request->toAttributes());

        return (new ClientResource($client))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): JsonResponse|ClientResource
    {
        $client = $this->clientService->findById($id);

        if ($client === null) {
            return $this->errorResponse("Cliente no encontrado: {$id}", 404);
        }

        $client->load('contacts');

        return new ClientResource($client);
    }

    public function update(string $id, ClientRequest $request): JsonResponse|ClientResource
    {
        $client = $this->clientService->findById($id);

        if ($client === null) {
            return $this->errorResponse("Cliente no encontrado: {$id}", 404);
        }

        $updated = $this->clientService->update($client, $request->toAttributes());

        return new ClientResource($updated);
    }

    public function destroy(string $id): JsonResponse
    {
        $client = $this->clientService->findById($id);

        if ($client === null) {
            return $this->errorResponse("Cliente no encontrado: {$id}", 404);
        }

        $this->clientService->delete($client);

        return $this->noContentResponse();
    }

    public function updateCredit(string $id, UpdateClientCreditRequest $request): JsonResponse|ClientResource
    {
        $client = $this->clientService->findById($id);

        if ($client === null) {
            return $this->errorResponse("Cliente no encontrado: {$id}", 404);
        }

        $data = $request->toAttributes();
        $updated = $this->clientService->updateCredit($client, $data['credit_limit'], $data['credit_days']);

        return new ClientResource($updated);
    }
}
