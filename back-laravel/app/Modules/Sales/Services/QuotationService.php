<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Clients\Models\Client;
use App\Modules\Clients\Models\ClientContact;
use App\Modules\Configuration\Services\CompanySettingService;
use App\Modules\Configuration\Services\SystemParameterService;
use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Services\ExchangeRateService;
use App\Modules\Inventory\Enums\PriceType;
use App\Modules\Inventory\Enums\TaxType;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Maintenance\Models\Employee;
use App\Modules\Sales\Enums\QuotationStatus;
use App\Modules\Sales\Exceptions\SalesRuleException;
use App\Modules\Sales\Models\Quotation;
use App\Modules\Sales\Models\QuotationItem;
use App\Modules\Users\Models\User;
use App\Support\Numbering\SequentialCodeGenerator;
use App\Support\Pricing\Decimal;
use App\Support\Pricing\DocumentTotals;
use App\Support\Pricing\DocumentTotalsCalculator;
use App\Support\Pricing\LineInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class QuotationService
{
    public function __construct(
        private readonly SalePricingService $pricingService,
        private readonly DiscountPolicyService $discountPolicyService,
        private readonly DocumentTotalsCalculator $totalsCalculator,
        private readonly SystemParameterService $parameterService,
        private readonly CompanySettingService $companySettingService,
        private readonly ExchangeRateService $exchangeRateService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws SalesRuleException
     */
    public function create(array $data, User $user, Branch $branch): Quotation
    {
        return DB::transaction(function () use ($data, $user, $branch): Quotation {
            $client = $this->resolveAndValidateClient((string) $data['client_id']);
            $contact = $this->resolveAndValidateContact($client, $data['contact_id'] ?? null);
            $sellerId = $this->resolveAndValidateSeller($user, $data['seller_id'] ?? null);
            $currency = $this->resolveCurrency((string) $data['currency_id']);
            $exchangeRate = $this->resolveExchangeRate($currency, $data['exchange_rate'] ?? null, (string) $data['issue_date']);

            $priceType = isset($data['price_type'])
                ? PriceType::from((string) $data['price_type'])
                : ($client->default_price_type ?? PriceType::Retail);

            $issueDate = (string) $data['issue_date'];
            $validUntil = isset($data['valid_until']) && trim((string) $data['valid_until']) !== ''
                ? (string) $data['valid_until']
                : $this->calculateDefaultValidUntil($issueDate);

            $company = $this->companySettingService->current();
            $taxRate = (string) ($company->default_tax_rate ?? 18.00);
            $pricesIncludeTax = (bool) ($data['prices_include_tax'] ?? $company->prices_include_tax ?? true);

            $processedLines = $this->processLines(
                array_values((array) $data['items']),
                $priceType,
                $currency,
                $exchangeRate
            );

            $globalDiscountPercent = (string) ($data['global_discount_percent'] ?? '0');
            $overrideUser = $this->discountPolicyService->validate($processedLines, $globalDiscountPercent, $user);

            $totals = $this->calculateTotals(
                $processedLines,
                $taxRate,
                $pricesIncludeTax,
                $globalDiscountPercent
            );

            $totalBaseCurrency = $this->calculateTotalBaseCurrency($totals->total, $currency, $exchangeRate);
            $number = SequentialCodeGenerator::next('quotation', 'COT');

            /** @var Quotation $quotation */
            $quotation = Quotation::create([
                'number' => $number,
                'branch_id' => $branch->id,
                'client_id' => $client->id,
                'contact_id' => $contact?->id,
                'seller_id' => $sellerId,
                'issue_date' => $issueDate,
                'valid_until' => $validUntil,
                'currency_id' => $currency->id,
                'exchange_rate' => $exchangeRate,
                'price_type' => $priceType->value,
                'prices_include_tax' => $pricesIncludeTax,
                'tax_rate' => $taxRate,
                'global_discount_percent' => $globalDiscountPercent,
                'taxable_base' => $totals->taxableBase,
                'exempt_base' => $totals->exemptBase,
                'unaffected_base' => $totals->unaffectedBase,
                'discount_total' => $totals->discountTotal,
                'tax_total' => $totals->taxTotal,
                'total' => $totals->total,
                'total_base_currency' => $totalBaseCurrency,
                'status' => QuotationStatus::Draft,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'delivery_time' => $data['delivery_time'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? null,
                'discount_override_by' => $overrideUser?->id,
                'created_by' => $user->id,
            ]);

            $this->createItems($quotation, $processedLines, $totals);

            return $quotation->fresh(['items.catalogItem', 'items.presentation', 'client', 'contact', 'seller', 'currency', 'createdBy']) ?? $quotation;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws SalesRuleException
     */
    public function update(Quotation $quotation, array $data, User $user): Quotation
    {
        if ($quotation->status !== QuotationStatus::Draft) {
            throw new SalesRuleException('Solo se pueden editar cotizaciones en estado borrador.');
        }

        return DB::transaction(function () use ($quotation, $data, $user): Quotation {
            $client = $this->resolveAndValidateClient((string) $data['client_id']);
            $contact = $this->resolveAndValidateContact($client, $data['contact_id'] ?? null);
            $sellerId = $this->resolveAndValidateSeller($user, $data['seller_id'] ?? null);
            $currency = $this->resolveCurrency((string) $data['currency_id']);
            $exchangeRate = $this->resolveExchangeRate($currency, $data['exchange_rate'] ?? null, (string) $data['issue_date']);

            $priceType = isset($data['price_type'])
                ? PriceType::from((string) $data['price_type'])
                : ($client->default_price_type ?? PriceType::Retail);

            $issueDate = (string) $data['issue_date'];
            $validUntil = isset($data['valid_until']) && trim((string) $data['valid_until']) !== ''
                ? (string) $data['valid_until']
                : $this->calculateDefaultValidUntil($issueDate);

            $company = $this->companySettingService->current();
            $taxRate = (string) ($company->default_tax_rate ?? 18.00);
            $pricesIncludeTax = (bool) ($data['prices_include_tax'] ?? $company->prices_include_tax ?? true);

            $processedLines = $this->processLines(
                array_values((array) $data['items']),
                $priceType,
                $currency,
                $exchangeRate
            );

            $globalDiscountPercent = (string) ($data['global_discount_percent'] ?? '0');
            $overrideUser = $this->discountPolicyService->validate($processedLines, $globalDiscountPercent, $user);

            $totals = $this->calculateTotals(
                $processedLines,
                $taxRate,
                $pricesIncludeTax,
                $globalDiscountPercent
            );

            $totalBaseCurrency = $this->calculateTotalBaseCurrency($totals->total, $currency, $exchangeRate);

            $quotation->update([
                'client_id' => $client->id,
                'contact_id' => $contact?->id,
                'seller_id' => $sellerId,
                'issue_date' => $issueDate,
                'valid_until' => $validUntil,
                'currency_id' => $currency->id,
                'exchange_rate' => $exchangeRate,
                'price_type' => $priceType->value,
                'prices_include_tax' => $pricesIncludeTax,
                'tax_rate' => $taxRate,
                'global_discount_percent' => $globalDiscountPercent,
                'taxable_base' => $totals->taxableBase,
                'exempt_base' => $totals->exemptBase,
                'unaffected_base' => $totals->unaffectedBase,
                'discount_total' => $totals->discountTotal,
                'tax_total' => $totals->taxTotal,
                'total' => $totals->total,
                'total_base_currency' => $totalBaseCurrency,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'delivery_time' => $data['delivery_time'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? null,
                'discount_override_by' => $overrideUser?->id,
            ]);

            $quotation->items()->delete();
            $this->createItems($quotation, $processedLines, $totals);

            return $quotation->fresh(['items.catalogItem', 'items.presentation', 'client', 'contact', 'seller', 'currency', 'createdBy']) ?? $quotation;
        });
    }

    /**
     * Duplica una cotización generando un nuevo borrador y omitiendo ítems inactivos.
     *
     * @return array{quotation: Quotation, skipped_lines: list<int>}
     *
     * @throws SalesRuleException
     */
    public function duplicate(Quotation $quotation, User $user, Branch $branch): array
    {
        $quotation->loadMissing(['items.presentation.item', 'items.catalogItem', 'client']);

        /** @var list<int> $skippedLines */
        $skippedLines = [];
        $itemsToDuplicate = [];

        foreach ($quotation->items as $item) {
            if ($item->item_presentation_id !== null) {
                $presentation = $item->presentation;
                $catalogItem = $presentation?->item;

                if (! $presentation || ! $presentation->is_active || ! $catalogItem || ! $catalogItem->is_active || ! $catalogItem->is_sellable) {
                    $skippedLines[] = $item->line;

                    continue;
                }

                $itemsToDuplicate[] = [
                    'item_presentation_id' => $presentation->id,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'discount_percent' => (float) $item->discount_percent,
                    'description' => $item->description,
                ];
            } else {
                $itemsToDuplicate[] = [
                    'description' => $item->description,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'discount_percent' => (float) $item->discount_percent,
                    'tax_type' => $item->tax_type->value,
                ];
            }
        }

        if (empty($itemsToDuplicate)) {
            throw new SalesRuleException('No se puede duplicar la cotización porque todas sus líneas contienen productos inactivos o no vendibles.');
        }

        $payload = [
            'client_id' => $quotation->client_id,
            'contact_id' => $quotation->contact_id,
            'seller_id' => $quotation->seller_id,
            'issue_date' => now()->toDateString(),
            'valid_until' => null, // se recalcula
            'currency_id' => $quotation->currency_id,
            'exchange_rate' => (float) $quotation->exchange_rate,
            'price_type' => is_string($quotation->price_type) ? $quotation->price_type : $quotation->price_type->value,
            'prices_include_tax' => $quotation->prices_include_tax,
            'global_discount_percent' => (float) $quotation->global_discount_percent,
            'notes' => $quotation->notes,
            'terms' => $quotation->terms,
            'delivery_time' => $quotation->delivery_time,
            'payment_terms' => $quotation->payment_terms,
            'items' => $itemsToDuplicate,
        ];

        $newQuotation = $this->create($payload, $user, $branch);

        return [
            'quotation' => $newQuotation,
            'skipped_lines' => $skippedLines,
        ];
    }

    /**
     * Retorna el stock disponible para cada línea de la cotización en los almacenes de la sede activa.
     *
     * @return list<array{line: int, available: float}>
     */
    public function availability(Quotation $quotation, Branch $branch): array
    {
        $quotation->loadMissing(['items.presentation']);

        $warehouseIds = Warehouse::where('branch_id', $branch->id)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        $result = [];

        foreach ($quotation->items as $item) {
            if ($item->catalog_item_id === null || empty($warehouseIds)) {
                $result[] = [
                    'line' => $item->line,
                    'available' => 0.0,
                ];

                continue;
            }

            $baseStock = (float) Stock::where('catalog_item_id', $item->catalog_item_id)
                ->whereIn('warehouse_id', $warehouseIds)
                ->sum('quantity');

            $factor = $item->presentation ? (float) $item->presentation->factor : 1.0;
            $available = $factor > 0 ? round($baseStock / $factor, 4) : $baseStock;

            $result[] = [
                'line' => $item->line,
                'available' => $available,
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Quotation>
     */
    public function query(array $filters, User $user, Branch $branch): Builder
    {
        $query = Quotation::query()
            ->forBranch($branch->id)
            ->visibleTo($user)
            ->with(['client', 'contact', 'seller', 'currency']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (! empty($filters['seller_id'])) {
            $query->where('seller_id', $filters['seller_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('issue_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('issue_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $query->search((string) $filters['search']);
        }

        $sortBy = $filters['sort_by'] ?? 'issue_date';
        $sortDirection = $filters['sort_direction'] ?? 'desc';

        if (in_array($sortBy, ['number', 'issue_date', 'valid_until', 'total', 'status', 'created_at'], true)) {
            $query->orderBy($sortBy, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc('issue_date')->orderByDesc('created_at');
        }

        return $query;
    }

    /**
     * Retorna el monto y cantidad acumulados por estado de cotización en moneda base.
     *
     * @return array<string, array{count: int, total: float}>
     */
    public function totalsByStatus(User $user, Branch $branch): array
    {
        $rawTotals = Quotation::query()
            ->forBranch($branch->id)
            ->visibleTo($user)
            ->selectRaw('status, count(*) as count, coalesce(sum(total_base_currency), 0) as total')
            ->groupBy('status')
            ->get();

        $result = [];
        foreach (QuotationStatus::cases() as $status) {
            $result[$status->value] = [
                'count' => 0,
                'total' => 0.0,
            ];
        }

        foreach ($rawTotals as $row) {
            $rawStatus = $row->getAttribute('status');
            $statusVal = $rawStatus instanceof QuotationStatus ? $rawStatus->value : (string) $rawStatus;
            $result[$statusVal] = [
                'count' => (int) $row->getAttribute('count'),
                'total' => round((float) $row->getAttribute('total'), 2),
            ];
        }

        return $result;
    }

    /**
     * @throws SalesRuleException
     */
    private function resolveAndValidateClient(string $clientId): Client
    {
        /** @var Client|null $client */
        $client = Client::query()->find($clientId);

        if (! $client || ! $client->is_active) {
            throw new SalesRuleException('El cliente seleccionado no existe o está inactivo.', 'client_id');
        }

        if ($client->is_system) {
            throw new SalesRuleException('No se pueden emitir cotizaciones al cliente de sistema.', 'client_id');
        }

        return $client;
    }

    /**
     * @throws SalesRuleException
     */
    private function resolveAndValidateContact(Client $client, ?string $contactId): ?ClientContact
    {
        if ($contactId === null || trim($contactId) === '') {
            return null;
        }

        /** @var ClientContact|null $contact */
        $contact = ClientContact::query()
            ->where('id', $contactId)
            ->where('client_id', $client->id)
            ->first();

        if (! $contact) {
            throw new SalesRuleException('El contacto seleccionado no pertenece al cliente.', 'contact_id');
        }

        return $contact;
    }

    /**
     * @throws SalesRuleException
     */
    private function resolveAndValidateSeller(User $user, ?string $sellerId): ?string
    {
        if ($sellerId !== null && trim($sellerId) !== '') {
            $exists = Employee::query()
                ->where('id', $sellerId)
                ->where('is_active', true)
                ->exists();

            if (! $exists) {
                throw new SalesRuleException('El vendedor seleccionado no es válido o está inactivo.', 'seller_id');
            }

            return $sellerId;
        }

        // Si no se especifica vendedor, se asigna el empleado vinculado al usuario autenticado (si está activo)
        $userEmployee = $user->employee;
        if ($userEmployee && $userEmployee->is_active) {
            return $userEmployee->id;
        }

        return null;
    }

    /**
     * @throws SalesRuleException
     */
    private function resolveCurrency(string $currencyId): Currency
    {
        /** @var Currency|null $currency */
        $currency = Currency::query()->find($currencyId);

        if (! $currency || ! $currency->is_active) {
            throw new SalesRuleException('La moneda seleccionada no es válida o está inactiva.', 'currency_id');
        }

        return $currency;
    }

    private function resolveExchangeRate(Currency $currency, mixed $explicitRate, string $issueDate): string
    {
        if ($currency->is_base) {
            return '1.0000';
        }

        if ($explicitRate !== null && (float) $explicitRate > 0) {
            return number_format((float) $explicitRate, 4, '.', '');
        }

        $currentRate = $this->exchangeRateService->current($currency->id, $issueDate);
        if ($currentRate['rate'] !== null) {
            return (string) $currentRate['rate']->sell_rate;
        }

        return '1.0000';
    }

    private function calculateDefaultValidUntil(string $issueDate): string
    {
        $days = $this->parameterService->integer('sales.quotation_validity_days', 15);

        return Carbon::parse($issueDate)->addDays($days)->format('Y-m-d');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     *
     * @throws SalesRuleException
     */
    private function processLines(
        array $items,
        PriceType $priceType,
        Currency $currency,
        string $exchangeRate
    ): array {
        $processed = [];

        foreach ($items as $index => $itemData) {
            $lineNum = $index + 1;
            $quantity = (float) $itemData['quantity'];
            $discountPercent = (string) ($itemData['discount_percent'] ?? '0');

            if (! empty($itemData['item_presentation_id'])) {
                /** @var ItemPresentation|null $presentation */
                $presentation = ItemPresentation::with('item')->find($itemData['item_presentation_id']);

                if (! $presentation || ! $presentation->is_active || ! $presentation->item || ! $presentation->item->is_active) {
                    throw new SalesRuleException("El producto de la línea {$lineNum} está inactivo.", "items.{$index}.item_presentation_id");
                }

                if (! $presentation->item->is_sellable) {
                    throw new SalesRuleException("El producto '{$presentation->item->name}' no está disponible para la venta.", "items.{$index}.item_presentation_id");
                }

                $listPrice = $this->pricingService->listPrice($presentation, $priceType, $quantity, $currency, $exchangeRate);
                $unitPrice = isset($itemData['unit_price']) && (float) $itemData['unit_price'] >= 0
                    ? (string) number_format((float) $itemData['unit_price'], 4, '.', '')
                    : (string) ($listPrice ?? '0.0000');

                $description = ! empty($itemData['description'])
                    ? (string) $itemData['description']
                    : ($presentation->item->name.' - '.$presentation->name);

                $taxType = $presentation->item->tax_type ?? TaxType::Taxable;
                $catalogItemId = $presentation->catalog_item_id;
                $presentationId = $presentation->id;
            } else {
                // Línea de texto libre
                $listPrice = null;
                $unitPrice = (string) number_format((float) ($itemData['unit_price'] ?? 0), 4, '.', '');
                $description = (string) ($itemData['description'] ?? '');
                $taxType = isset($itemData['tax_type'])
                    ? TaxType::from((string) $itemData['tax_type'])
                    : TaxType::Taxable;
                $catalogItemId = null;
                $presentationId = null;
            }

            $effectiveDiscount = $this->discountPolicyService->calculateEffectiveDiscount($listPrice, $unitPrice, $discountPercent);

            $processed[] = [
                'line' => $lineNum,
                'catalog_item_id' => $catalogItemId,
                'item_presentation_id' => $presentationId,
                'description' => $description,
                'quantity' => (string) $quantity,
                'list_price' => $listPrice,
                'unit_price' => $unitPrice,
                'discount_percent' => $discountPercent,
                'effective_discount_percent' => (string) $effectiveDiscount,
                'tax_type' => $taxType,
            ];
        }

        return $processed;
    }

    /**
     * @param  list<array<string, mixed>>  $processedLines
     */
    private function calculateTotals(
        array $processedLines,
        string $taxRate,
        bool $pricesIncludeTax,
        string $globalDiscountPercent
    ): DocumentTotals {
        $inputs = [];
        foreach ($processedLines as $line) {
            $inputs[] = new LineInput(
                quantity: (string) $line['quantity'],
                unitPrice: (string) $line['unit_price'],
                discountPercent: (string) $line['discount_percent'],
                discountAmount: null,
                taxType: $line['tax_type']
            );
        }

        return $this->totalsCalculator->calculate(
            lines: $inputs,
            taxRate: $taxRate,
            pricesIncludeTax: $pricesIncludeTax,
            globalDiscount: $globalDiscountPercent,
            globalDiscountType: 'percent'
        );
    }

    private function calculateTotalBaseCurrency(string $total, Currency $currency, string $exchangeRate): string
    {
        if ($currency->is_base || $exchangeRate === '1.0000' || (float) $exchangeRate === 1.0) {
            return $total;
        }

        return Decimal::mul($total, $exchangeRate, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $processedLines
     */
    private function createItems(Quotation $quotation, array $processedLines, DocumentTotals $totals): void
    {
        foreach ($processedLines as $i => $line) {
            $lineResult = $totals->lines[$i];

            QuotationItem::create([
                'quotation_id' => $quotation->id,
                'line' => $line['line'],
                'catalog_item_id' => $line['catalog_item_id'],
                'item_presentation_id' => $line['item_presentation_id'],
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'list_price' => $line['list_price'],
                'unit_price' => $line['unit_price'],
                'discount_percent' => $line['discount_percent'],
                'effective_discount_percent' => $line['effective_discount_percent'],
                'tax_type' => $line['tax_type'],
                'line_subtotal' => $lineResult->subtotal,
                'line_tax' => $lineResult->tax,
                'line_total' => $lineResult->total,
            ]);
        }
    }
}
