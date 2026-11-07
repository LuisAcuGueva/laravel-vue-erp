<?php

declare(strict_types=1);

namespace App\Modules\Clients\Services;

use App\Modules\Clients\Exceptions\ClientRuleException;
use App\Modules\Clients\Models\Client;
use App\Modules\Sales\Models\Quotation;
use App\Modules\Sales\Models\Sale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ClientService
{
    /**
     * Filtros y orden del listado, sin paginar. Lo reutilizan `list()` y la exportación CSV.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Client>
     */
    public function query(array $filters = []): Builder
    {
        $query = Client::query()->search($filters['search'] ?? null);

        if (! empty($filters['client_type'])) {
            $query->where('client_type', $filters['client_type']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderByDesc('created_at');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Client>
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = (int) ($filters['page'] ?? 1);

        return $this->query($filters)->paginate(perPage: $perPage, page: $page);
    }

    /**
     * @return Collection<int, Client>
     */
    public function options(?string $search = null): Collection
    {
        $query = Client::query()->active();

        if ($search !== null && trim($search) !== '') {
            $term = trim($search);
            $query->search($term);

            $query->orderByRaw(
                'CASE WHEN document_number = ? THEN 0 ELSE 1 END',
                [$term]
            )->orderBy('name');
        } else {
            $query->orderByDesc('is_system')
                ->orderByDesc('created_at');
        }

        return $query->limit(20)->get();
    }

    public function findById(string $id): ?Client
    {
        return Client::find($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ClientRuleException
     */
    public function create(array $attributes): Client
    {
        $clientType = $attributes['client_type'] ?? null;
        $docType = $attributes['document_type'] ?? null;

        if ($clientType === 'company' && $docType !== 'RUC') {
            throw new ClientRuleException('Una empresa debe tener tipo de documento RUC.', 'document_type');
        }

        return Client::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ClientRuleException
     */
    public function update(Client $client, array $attributes): Client
    {
        if ($client->is_system) {
            throw new ClientRuleException('El cliente genérico no se puede modificar.', 'client');
        }

        $clientType = $attributes['client_type'] ?? $client->client_type->value;
        $docType = $attributes['document_type'] ?? $client->document_type?->value;

        if ($clientType === 'company' && $docType !== 'RUC') {
            throw new ClientRuleException('Una empresa debe tener tipo de documento RUC.', 'document_type');
        }

        $client->update($attributes);

        return $client->fresh() ?? $client;
    }

    /**
     * @throws ClientRuleException
     */
    public function updateCredit(Client $client, string|float $limit, int $days): Client
    {
        if ($client->is_system) {
            throw new ClientRuleException('No se puede modificar el crédito del cliente genérico.', 'client');
        }

        $client->forceFill([
            'credit_limit' => $limit,
            'credit_days' => $days,
        ])->save();

        return $client->fresh() ?? $client;
    }

    /**
     * @throws ClientRuleException
     */
    public function delete(Client $client): bool
    {
        if ($client->is_system) {
            throw new ClientRuleException('El cliente genérico no se puede eliminar.', 'client');
        }

        if ($this->hasDocuments($client)) {
            throw new ClientRuleException('No se puede eliminar un cliente con documentos asociados.', 'client');
        }

        return (bool) $client->delete();
    }

    public function hasDocuments(Client $client): bool
    {
        return Quotation::where('client_id', $client->id)->exists()
            || Sale::where('client_id', $client->id)->exists();
    }
}
