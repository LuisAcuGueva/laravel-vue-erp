<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Configuration\Services\SystemParameterService;
use App\Modules\Finance\Data\AccountingSetupIssue;
use App\Modules\Finance\Enums\SystemConcept;
use App\Modules\Finance\Models\FinanceConcept;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Support\AccountCode;
use Illuminate\Support\Collection;

/**
 * Diagnóstico de la configuración contable (spec 032). Una consulta por problema.
 */
class AccountingSetupHealthService
{
    private const MAX_ITEMS = 50;

    public function __construct(
        private readonly SystemParameterService $parameters,
    ) {}

    /**
     * @return array{status: 'ok'|'warning'|'error', issues: list<AccountingSetupIssue>}
     */
    public function check(): array
    {
        $required = $this->parameters->boolean('finance.require_concept_account');

        $issues = array_values(array_filter([
            $this->conceptsWithoutAccount($required),
            $this->conceptsOnNonPostableAccount(),
            $this->conceptsOnInactiveAccount(),
            $this->conceptsWithTypeMismatch(),
            $this->postableParentAccounts(),
            $this->accountsWithInvalidCode(),
            $this->reassignedSystemConcepts(),
        ]));

        $status = 'ok';
        foreach ($issues as $issue) {
            if ($issue->severity === AccountingSetupIssue::ERROR) {
                $status = 'error';
                break;
            }
            $status = 'warning';
        }

        return ['status' => $status, 'issues' => $issues];
    }

    /**
     * Payload serializable usado por el endpoint y el comando.
     *
     * @return array{status: string, require_concept_account: bool, issues: list<array<string, mixed>>}
     */
    public function payload(): array
    {
        $result = $this->check();

        return [
            'status' => $result['status'],
            'require_concept_account' => $this->parameters->boolean('finance.require_concept_account'),
            'issues' => array_map(fn (AccountingSetupIssue $issue): array => $issue->toArray(), $result['issues']),
        ];
    }

    private function conceptsWithoutAccount(bool $required): ?AccountingSetupIssue
    {
        $concepts = FinanceConcept::query()->active()->withoutAccount()->orderBy('code')->get();

        return $this->issue(
            'concept_without_account',
            $required ? AccountingSetupIssue::ERROR : AccountingSetupIssue::WARNING,
            'Hay {n} conceptos activos sin cuenta contable.',
            $concepts,
        );
    }

    private function conceptsOnNonPostableAccount(): ?AccountingSetupIssue
    {
        $concepts = FinanceConcept::query()
            ->whereHas('financialAccount', fn ($a) => $a->where('is_postable', false))
            ->orderBy('code')
            ->get();

        return $this->issue(
            'concept_account_not_postable',
            AccountingSetupIssue::ERROR,
            'Hay {n} conceptos imputados a una cuenta no imputable.',
            $concepts,
        );
    }

    private function conceptsOnInactiveAccount(): ?AccountingSetupIssue
    {
        $concepts = FinanceConcept::query()
            ->whereNotNull('financial_account_id')
            ->whereDoesntHave('financialAccount', fn ($a) => $a->where('is_active', true))
            ->orderBy('code')
            ->get();

        return $this->issue(
            'concept_account_inactive',
            AccountingSetupIssue::ERROR,
            'Hay {n} conceptos imputados a una cuenta inactiva o eliminada.',
            $concepts,
        );
    }

    private function conceptsWithTypeMismatch(): ?AccountingSetupIssue
    {
        $concepts = FinanceConcept::query()
            ->active()
            ->whereNotNull('financial_account_id')
            ->with('financialAccount')
            ->orderBy('code')
            ->get()
            ->filter(fn (FinanceConcept $c): bool => $c->financialAccount !== null
                && ! $c->direction->allowsAccount($c->financialAccount))
            ->values();

        return $this->issue(
            'concept_account_type_mismatch',
            AccountingSetupIssue::ERROR,
            'Hay {n} conceptos cuya cuenta no es válida para su dirección.',
            $concepts,
        );
    }

    private function postableParentAccounts(): ?AccountingSetupIssue
    {
        $accounts = FinancialAccount::query()
            ->where('is_postable', true)
            ->whereHas('children', fn ($c) => $c->where('is_active', true))
            ->orderBy('code')
            ->get();

        return $this->issue(
            'parent_account_postable',
            AccountingSetupIssue::ERROR,
            'Hay {n} cuentas con subcuentas marcadas como imputables.',
            $accounts,
        );
    }

    private function accountsWithInvalidCode(): ?AccountingSetupIssue
    {
        $accounts = FinancialAccount::query()
            ->with('parent')
            ->orderBy('code')
            ->get()
            ->filter(fn (FinancialAccount $a): bool => ! AccountCode::isValidChildOf($a->code, $a->parent?->code))
            ->values();

        return $this->issue(
            'account_code_format',
            AccountingSetupIssue::ERROR,
            'Hay {n} cuentas con un código fuera del formato PCGE.',
            $accounts,
        );
    }

    private function reassignedSystemConcepts(): ?AccountingSetupIssue
    {
        $concepts = FinanceConcept::query()
            ->where('is_system', true)
            ->whereNotNull('financial_account_id')
            ->with('financialAccount')
            ->orderBy('code')
            ->get()
            ->filter(function (FinanceConcept $c): bool {
                $system = SystemConcept::tryFrom($c->code);

                return $system !== null && $c->financialAccount?->code !== $system->defaultAccountCode();
            })
            ->values();

        return $this->issue(
            'system_concept_reassigned',
            AccountingSetupIssue::WARNING,
            'Hay {n} conceptos de sistema imputados a una cuenta distinta de la predeterminada.',
            $concepts,
        );
    }

    /**
     * @param  Collection<int, FinanceConcept|FinancialAccount>|\Illuminate\Database\Eloquent\Collection<int, FinanceConcept>|\Illuminate\Database\Eloquent\Collection<int, FinancialAccount>  $models
     */
    private function issue(string $code, string $severity, string $message, iterable $models): ?AccountingSetupIssue
    {
        $items = [];
        $count = 0;

        foreach ($models as $model) {
            $count++;
            if ($count <= self::MAX_ITEMS) {
                $items[] = ['id' => $model->id, 'code' => $model->code, 'name' => $model->name];
            }
        }

        if ($count === 0) {
            return null;
        }

        return new AccountingSetupIssue($code, $severity, str_replace('{n}', (string) $count, $message), $count, $items);
    }
}
