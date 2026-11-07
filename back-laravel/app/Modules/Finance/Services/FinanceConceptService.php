<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Configuration\Services\SystemParameterService;
use App\Modules\Finance\Enums\ConceptDirection;
use App\Modules\Finance\Enums\FinancialAccountType;
use App\Modules\Finance\Exceptions\FinanceRuleException;
use App\Modules\Finance\Models\FinanceConcept;
use App\Modules\Finance\Models\FinancialAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class FinanceConceptService
{
    public function __construct(
        protected SystemParameterService $parameters,
    ) {}

    /**
     * @return Builder<FinanceConcept>
     */
    public function query(): Builder
    {
        return FinanceConcept::with('financialAccount');
    }

    /**
     * @return LengthAwarePaginator<int, FinanceConcept>
     */
    public function paginate(
        ?string $search = null,
        ?string $direction = null,
        ?bool $active = null,
        int $perPage = 15,
        ?bool $hasAccount = null,
        FinancialAccountType|string|null $accountType = null,
    ): LengthAwarePaginator {
        $query = $this->query();

        if ($search !== null && trim($search) !== '') {
            $query->search($search);
        }

        if ($direction !== null && trim($direction) !== '') {
            $query->direction($direction);
        }

        if ($active !== null) {
            $query->where('is_active', $active);
        }

        if ($hasAccount !== null) {
            $hasAccount ? $query->whereNotNull('financial_account_id') : $query->withoutAccount();
        }

        $this->applyAccountType($query, $accountType);

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * @return Collection<int, FinanceConcept>
     */
    public function options(
        ?string $direction = null,
        bool $includeSystem = false,
        FinancialAccountType|string|null $accountType = null,
    ): Collection {
        $query = FinanceConcept::with('financialAccount')
            ->active();

        if ($direction !== null && trim($direction) !== '') {
            $query->direction($direction);
        }

        if (! $includeSystem) {
            $query->where('is_system', false);
        }

        $this->applyAccountType($query, $accountType);

        return $query->orderBy('name')->get();
    }

    /**
     * Con la imputación obligatoria activa, los conceptos sin cuenta no cuentan como válidos para ningún tipo.
     *
     * @param  Builder<FinanceConcept>  $query
     */
    protected function applyAccountType(Builder $query, FinancialAccountType|string|null $accountType): void
    {
        if ($accountType === null || $accountType === '') {
            return;
        }

        $query->accountType($accountType);

        if ($this->requiresAccount()) {
            $query->whereNotNull('financial_account_id');
        }
    }

    protected function requiresAccount(): bool
    {
        return $this->parameters->boolean('finance.require_concept_account');
    }

    public function find(string $id): FinanceConcept
    {
        return FinanceConcept::with('financialAccount')->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): FinanceConcept
    {
        $this->validateFinancialAccount($attributes);

        /** @var FinanceConcept $concept */
        $concept = FinanceConcept::create($attributes);

        return $concept->load('financialAccount');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(FinanceConcept $concept, array $attributes): FinanceConcept
    {
        if ($concept->is_system) {
            if (isset($attributes['code']) && $attributes['code'] !== $concept->code) {
                throw new FinanceRuleException('No se puede modificar el código de un concepto de sistema', 'code');
            }

            if (isset($attributes['direction'])) {
                $dirValue = $attributes['direction'] instanceof ConceptDirection ? $attributes['direction']->value : $attributes['direction'];
                if ($dirValue !== $concept->direction->value) {
                    throw new FinanceRuleException('No se puede modificar la dirección de un concepto de sistema', 'direction');
                }
            }
        }

        $merged = array_merge($concept->toArray(), $attributes);
        $this->validateFinancialAccount($merged);

        $concept->update($attributes);

        return $concept->fresh('financialAccount') ?? $concept;
    }

    public function delete(FinanceConcept $concept): bool
    {
        if ($concept->is_system) {
            throw new FinanceRuleException('No se puede eliminar un concepto de sistema', 'finance_concept');
        }

        return (bool) $concept->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function validateFinancialAccount(array $attributes): void
    {
        $accountId = $attributes['financial_account_id'] ?? null;
        if ($accountId === null || trim((string) $accountId) === '') {
            if ($this->requiresAccount()) {
                throw new FinanceRuleException('La cuenta contable es obligatoria.', 'financial_account_id');
            }

            return;
        }

        /** @var FinancialAccount|null $account */
        $account = FinancialAccount::query()->find($accountId);
        if ($account === null || ! $account->is_active) {
            throw new FinanceRuleException('La cuenta financiera especificada no existe o no está activa', 'financial_account_id');
        }

        if (! $account->is_postable) {
            throw new FinanceRuleException('Solo las cuentas imputables pueden asignarse a conceptos', 'financial_account_id');
        }

        $direction = $attributes['direction'] ?? null;
        $direction = $direction instanceof ConceptDirection ? $direction : ConceptDirection::tryFrom((string) $direction);

        if ($direction !== null && ! $direction->allowsAccount($account)) {
            throw new FinanceRuleException(
                "Una cuenta de tipo {$account->type->label()} no es válida para un concepto de {$direction->label()}.",
                'financial_account_id'
            );
        }
    }
}
