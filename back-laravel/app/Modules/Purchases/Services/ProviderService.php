<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Services;

use App\Modules\Purchases\Exceptions\PurchasesRuleException;
use App\Modules\Purchases\Models\Provider;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\SupplierInvoice;
use App\Modules\Purchases\Models\SupplierPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ProviderService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Provider>
     */
    public function query(array $filters = []): Builder
    {
        $query = Provider::query()
            ->with(['primaryContact', 'defaultCurrency:id,code,symbol'])
            ->search(isset($filters['search']) ? (string) $filters['search'] : null);

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('business_name')->orderBy('id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Provider>
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = (int) ($filters['page'] ?? 1);

        return $this->query($filters)->paginate(perPage: $perPage, page: $page);
    }

    /**
     * Hasta 20 proveedores activos; la coincidencia exacta de documento va primero.
     *
     * @return Collection<int, Provider>
     */
    public function options(?string $search = null): Collection
    {
        $query = Provider::query()->active()->search($search);

        if ($search !== null && trim($search) !== '') {
            $query->orderByRaw('CASE WHEN document_number = ? THEN 0 ELSE 1 END', [trim($search)]);
        }

        return $query->orderBy('business_name')
            ->limit(20)
            ->get(['id', 'document_type', 'document_number', 'business_name', 'default_currency_id', 'credit_days']);
    }

    public function findById(string $id): ?Provider
    {
        return Provider::query()
            ->with(['contacts', 'bankAccounts.bank:id,name,short_name', 'bankAccounts.currency:id,code,symbol', 'defaultCurrency:id,code,symbol'])
            ->find($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Provider
    {
        return Provider::create($attributes)->load(['contacts', 'bankAccounts', 'defaultCurrency']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Provider $provider, array $attributes): Provider
    {
        $provider->update($attributes);

        return $this->findById($provider->id) ?? $provider;
    }

    /**
     * @throws PurchasesRuleException
     */
    public function delete(Provider $provider): bool
    {
        if ($this->hasDocuments($provider)) {
            throw new PurchasesRuleException('No se puede eliminar un proveedor con órdenes o facturas; desactívelo en su lugar', 'provider');
        }

        return (bool) $provider->delete();
    }

    /**
     * Órdenes de compra (spec 022), facturas y pagos (spec 024) impiden eliminar al proveedor.
     */
    protected function hasDocuments(Provider $provider): bool
    {
        return PurchaseOrder::query()->where('provider_id', $provider->id)->exists()
            || SupplierInvoice::query()->where('provider_id', $provider->id)->exists()
            || SupplierPayment::query()->where('provider_id', $provider->id)->exists();
    }
}
