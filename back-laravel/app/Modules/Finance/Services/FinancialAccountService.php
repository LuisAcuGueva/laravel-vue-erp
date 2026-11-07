<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Enums\FinancialAccountType;
use App\Modules\Finance\Exceptions\FinanceRuleException;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Support\AccountCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FinancialAccountService
{
    /**
     * @return Builder<FinancialAccount>
     */
    public function query(): Builder
    {
        return FinancialAccount::with('parent');
    }

    /**
     * @return LengthAwarePaginator<int, FinancialAccount>
     */
    public function paginate(
        ?string $search = null,
        ?string $type = null,
        ?bool $postable = null,
        ?bool $active = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = $this->query();

        if ($search !== null && trim($search) !== '') {
            $query->search($search);
        }

        if ($type !== null && trim($type) !== '') {
            $query->where('type', $type);
        }

        if ($postable !== null) {
            $query->where('is_postable', $postable);
        }

        if ($active !== null) {
            $query->where('is_active', $active);
        }

        return $query->orderBy('code')->paginate($perPage);
    }

    /**
     * Devuelve el árbol jerárquico completo del plan de cuentas.
     *
     * @return Collection<int, FinancialAccount>
     */
    public function tree(): Collection
    {
        /** @var Collection<int, FinancialAccount> $accounts */
        $accounts = FinancialAccount::query()
            ->active()
            ->orderBy('code')
            ->get();

        $accountMap = [];
        foreach ($accounts as $account) {
            $account->setRelation('children', new Collection);
            $accountMap[$account->id] = $account;
        }

        /** @var Collection<int, FinancialAccount> $roots */
        $roots = new Collection;
        foreach ($accounts as $account) {
            if ($account->parent_id !== null && isset($accountMap[$account->parent_id])) {
                /** @var Collection<int, FinancialAccount> $children */
                $children = $accountMap[$account->parent_id]->children;
                $children->push($account);
            } else {
                $roots->push($account);
            }
        }

        return $roots;
    }

    /**
     * @return Collection<int, FinancialAccount>
     */
    public function options(?string $type = null, bool $postableOnly = false): Collection
    {
        $query = FinancialAccount::query()->active();

        if ($type !== null && trim($type) !== '') {
            $query->where('type', $type);
        }

        if ($postableOnly) {
            $query->postable();
        }

        return $query->orderBy('code')->get();
    }

    public function find(string $id): FinancialAccount
    {
        return FinancialAccount::with(['parent', 'children'])->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): FinancialAccount
    {
        return DB::transaction(function () use ($attributes) {
            unset($attributes['level']);
            $code = (string) $attributes['code'];
            $parentId = $attributes['parent_id'] ?? null;
            $parent = null;

            if ($parentId !== null && $parentId !== '') {
                /** @var FinancialAccount $parent */
                $parent = FinancialAccount::query()->lockForUpdate()->findOrFail($parentId);

                if (! $parent->is_active) {
                    throw new FinanceRuleException('La cuenta padre especificada no está activa', 'parent_id');
                }

                $this->assertTypeMatchesParent($attributes, $parent);
                $this->assertParentAcceptsChildren($parent);

                $attributes['type'] = $parent->type;
            } else {
                $attributes['parent_id'] = null;

                if (! isset($attributes['type'])) {
                    throw new FinanceRuleException('El tipo de cuenta es obligatorio para cuentas principales', 'type');
                }
            }

            $this->assertCodeFitsParent($code, $parent);
            $attributes['level'] = AccountCode::levelFor($parent);

            /** @var FinancialAccount $account */
            $account = FinancialAccount::create($attributes);

            // El padre pasa a no ser imputable al tener hijas
            if ($parent !== null && $parent->is_postable) {
                $parent->update(['is_postable' => false]);
            }

            return $account->load('parent');
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(FinancialAccount $account, array $attributes): FinancialAccount
    {
        return DB::transaction(function () use ($account, $attributes) {
            unset($attributes['level']);

            $newCode = isset($attributes['code']) ? (string) $attributes['code'] : $account->code;
            $codeChanged = $newCode !== $account->code;
            $parentChanged = false;
            $previousParent = null;
            $effectiveParent = $account->parent;

            if ($codeChanged && $account->children()->exists()) {
                throw new FinanceRuleException('No se puede cambiar el código de una cuenta con subcuentas', 'code');
            }

            if (array_key_exists('parent_id', $attributes)) {
                $newParentId = $attributes['parent_id'] ?? null;

                if ($newParentId === '') {
                    $newParentId = null;
                }

                if ($newParentId !== $account->parent_id) {
                    $parentChanged = true;
                    $previousParent = $account->parent;

                    if ($newParentId === $account->id) {
                        throw new FinanceRuleException('Una cuenta no puede ser su propio ancestro (ciclo detectado)', 'parent_id');
                    }

                    if ($newParentId !== null) {
                        /** @var FinancialAccount $newParent */
                        $newParent = FinancialAccount::query()->lockForUpdate()->findOrFail($newParentId);

                        if (! $newParent->is_active) {
                            throw new FinanceRuleException('La cuenta padre especificada no está activa', 'parent_id');
                        }

                        if (in_array($account->id, $newParent->ancestorIds(), true)) {
                            throw new FinanceRuleException('Una cuenta no puede ser su propio ancestro (ciclo detectado)', 'parent_id');
                        }

                        $this->assertTypeMatchesParent($attributes, $newParent);
                        $this->assertParentAcceptsChildren($newParent);

                        $attributes['type'] = $newParent->type;
                        $effectiveParent = $newParent;
                    } else {
                        $attributes['parent_id'] = null;
                        $effectiveParent = null;
                    }
                }
            }

            if ($codeChanged || $parentChanged) {
                $this->assertCodeFitsParent($newCode, $effectiveParent);
            }

            if ($parentChanged) {
                $newLevel = AccountCode::levelFor($effectiveParent);
                $levelDelta = $newLevel - $account->level;
                $attributes['level'] = $newLevel;

                if ($levelDelta !== 0) {
                    $this->updateDescendantLevels($account, $levelDelta);
                }
            }

            if (array_key_exists('is_active', $attributes) && ! $attributes['is_active'] && $account->is_active) {
                $this->assertCanDeactivate($account);
            }

            if (array_key_exists('is_postable', $attributes)) {
                $this->assertPostableAllowed($account, (bool) $attributes['is_postable']);
            }

            $account->update($attributes);

            if ($effectiveParent !== null && $effectiveParent->is_postable) {
                $effectiveParent->update(['is_postable' => false]);
            }

            if ($previousParent !== null && $previousParent->id !== $effectiveParent?->id) {
                $this->restorePostableIfLeaf($previousParent);
            }

            return $account->fresh(['parent', 'children']) ?? $account;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function assertTypeMatchesParent(array $attributes, FinancialAccount $parent): void
    {
        if (! isset($attributes['type'])) {
            return;
        }

        $givenType = $attributes['type'] instanceof FinancialAccountType ? $attributes['type']->value : (string) $attributes['type'];
        if ($givenType !== $parent->type->value) {
            throw new FinanceRuleException('Una cuenta hija hereda el tipo de la cuenta padre', 'type');
        }
    }

    protected function assertParentAcceptsChildren(FinancialAccount $parent): void
    {
        $concepts = $parent->concepts()->count();

        if ($concepts > 0) {
            throw new FinanceRuleException(
                "La cuenta {$parent->code} tiene {$concepts} conceptos asociados. Reasígnalos antes de crear subcuentas.",
                'parent_id'
            );
        }
    }

    protected function assertCodeFitsParent(string $code, ?FinancialAccount $parent): void
    {
        if (AccountCode::isValidChildOf($code, $parent?->code)) {
            return;
        }

        throw new FinanceRuleException(
            $parent === null
                ? 'Una cuenta principal debe tener 2 dígitos.'
                : "El código debe comenzar con el código de la cuenta padre ({$parent->code}).",
            'code'
        );
    }

    protected function assertCanDeactivate(FinancialAccount $account): void
    {
        $concepts = $account->concepts()->where('is_active', true)->count();
        if ($concepts > 0) {
            throw new FinanceRuleException(
                "No se puede desactivar la cuenta {$account->code}: tiene {$concepts} conceptos activos asociados.",
                'is_active'
            );
        }

        if ($account->children()->where('is_active', true)->exists()) {
            throw new FinanceRuleException(
                "No se puede desactivar la cuenta {$account->code}: tiene subcuentas activas.",
                'is_active'
            );
        }
    }

    protected function assertPostableAllowed(FinancialAccount $account, bool $postable): void
    {
        if ($postable && $account->children()->where('is_active', true)->exists()) {
            throw new FinanceRuleException('Una cuenta con subcuentas no puede ser imputable.', 'is_postable');
        }

        if (! $postable && $account->is_postable) {
            $concepts = $account->concepts()->count();
            if ($concepts > 0) {
                throw new FinanceRuleException(
                    "La cuenta {$account->code} tiene {$concepts} conceptos asociados; debe seguir siendo imputable.",
                    'is_postable'
                );
            }
        }
    }

    protected function restorePostableIfLeaf(FinancialAccount $parent): void
    {
        if (! $parent->children()->where('is_active', true)->exists()) {
            $parent->update(['is_postable' => true]);
        }
    }

    protected function updateDescendantLevels(FinancialAccount $parent, int $delta): void
    {
        foreach ($parent->children as $child) {
            $child->update(['level' => $child->level + $delta]);
            $this->updateDescendantLevels($child, $delta);
        }
    }

    public function delete(FinancialAccount $account): bool
    {
        if ($account->children()->exists()) {
            throw new FinanceRuleException('No se puede eliminar una cuenta con subcuentas', 'financial_account');
        }

        if ($account->concepts()->exists()) {
            throw new FinanceRuleException('No se puede eliminar una cuenta financiera con conceptos asociados', 'financial_account');
        }

        return (bool) $account->delete();
    }
}
