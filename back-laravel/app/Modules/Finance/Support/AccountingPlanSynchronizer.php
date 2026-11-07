<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Enums\ConceptDirection;
use App\Modules\Finance\Enums\FinancialAccountType;
use App\Modules\Finance\Enums\SystemConcept;
use App\Modules\Finance\Models\FinanceConcept;
use App\Modules\Finance\Models\FinancialAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Lógica compartida por los seeders y la migración de normalización del plan de cuentas.
 * Es idempotente y nunca pisa decisiones del usuario (nombre, estado, cuenta de un concepto).
 */
class AccountingPlanSynchronizer
{
    /**
     * Cuenta padre → hoja imputable por defecto (requirements §4.4). PHP convierte las claves numéricas en enteros.
     *
     * @var array<int|string, string>
     */
    public const DEFAULT_LEAVES = [
        '10' => '101',
        '104' => '1041',
        '12' => '1212',
        '121' => '1212',
        '42' => '4212',
        '421' => '4212',
        '63' => '6391',
        '635' => '6352',
        '636' => '6361',
        '65' => '659',
        '70' => '7011',
        '701' => '7011',
        '75' => '759',
    ];

    /**
     * @param  list<array{code: string, name: string, type: string}>  $catalog
     */
    public function syncAccounts(array $catalog): void
    {
        DB::transaction(function () use ($catalog): void {
            usort($catalog, fn (array $a, array $b): int => [strlen($a['code']), $a['code']] <=> [strlen($b['code']), $b['code']]);

            $codes = array_map(fn (array $row): string => $row['code'], $catalog);

            /** @var array<string, FinancialAccount> $live */
            $live = [];
            /** @var array<string, true> $trashed */
            $trashed = [];

            foreach (FinancialAccount::withTrashed()->whereIn('code', $codes)->get() as $account) {
                if ($account->trashed()) {
                    $trashed[$account->code] = true;

                    continue;
                }
                $live[$account->code] = $account;
            }

            foreach ($catalog as $row) {
                $code = $row['code'];
                $type = FinancialAccountType::from($row['type']);
                $parentCode = AccountCode::parentOf($code, $codes);
                $parent = $parentCode !== null ? ($live[$parentCode] ?? null) : null;
                $level = AccountCode::levelFor($parent);

                if (isset($live[$code])) {
                    $live[$code]->update([
                        'parent_id' => $parent?->id,
                        'level' => $level,
                        'type' => $type,
                    ]);

                    continue;
                }

                if (isset($trashed[$code])) {
                    Log::info("Plan de cuentas: la cuenta {$code} fue eliminada por el usuario; no se restaura.");

                    continue;
                }

                $live[$code] = FinancialAccount::create([
                    'code' => $code,
                    'name' => $row['name'],
                    'type' => $type,
                    'parent_id' => $parent?->id,
                    'level' => $level,
                    'is_postable' => true,
                    'is_active' => true,
                ]);
            }

            $this->recalculatePostable();
        });
    }

    /**
     * Una cuenta es imputable si y solo si no tiene hijas activas.
     */
    public function recalculatePostable(): void
    {
        DB::update(
            'UPDATE financial_accounts SET is_postable = NOT EXISTS ('
            .'SELECT 1 FROM financial_accounts c WHERE c.parent_id = financial_accounts.id '
            .'AND c.is_active = true AND c.deleted_at IS NULL) '
            .'WHERE deleted_at IS NULL'
        );
    }

    /**
     * Mueve los conceptos imputados a una cuenta no imputable hacia su hoja por defecto.
     *
     * @param  array<int|string, string>  $fallbacks
     */
    public function relocateConceptsFromParents(array $fallbacks): int
    {
        return DB::transaction(function () use ($fallbacks): int {
            $moved = 0;

            foreach ($fallbacks as $parentKey => $leafCode) {
                $parentCode = (string) $parentKey;
                /** @var FinancialAccount|null $parent */
                $parent = FinancialAccount::where('code', $parentCode)->first();
                /** @var FinancialAccount|null $leaf */
                $leaf = FinancialAccount::where('code', $leafCode)->first();

                if ($parent === null || $leaf === null || $parent->is_postable || ! $leaf->is_postable || ! $leaf->is_active) {
                    continue;
                }

                foreach (FinanceConcept::where('financial_account_id', $parent->id)->get() as $concept) {
                    $concept->update(['financial_account_id' => $leaf->id]);
                    $moved++;
                    Log::warning("Plan de cuentas: el concepto {$concept->code} pasó de la cuenta {$parentCode} a {$leafCode}.");
                }
            }

            return $moved;
        });
    }

    /**
     * Renombra una cuenta conservando su id; si el código destino ya existe, fusiona en él.
     */
    public function renameOrMerge(string $old, string $new, ?string $parentCode): void
    {
        DB::transaction(function () use ($old, $new, $parentCode): void {
            /** @var FinancialAccount|null $source */
            $source = FinancialAccount::where('code', $old)->first();
            if ($source === null) {
                return;
            }

            $parentId = $parentCode !== null
                ? FinancialAccount::where('code', $parentCode)->value('id')
                : null;

            /** @var FinancialAccount|null $target */
            $target = FinancialAccount::where('code', $new)->first();

            if ($target === null) {
                $source->update(['code' => $new, 'parent_id' => $parentId]);
                Log::info("Plan de cuentas: la cuenta {$old} pasó a {$new}.");

                return;
            }

            FinanceConcept::where('financial_account_id', $source->id)
                ->update(['financial_account_id' => $target->id]);
            FinancialAccount::where('parent_id', $source->id)
                ->update(['parent_id' => $target->id]);
            $source->delete();

            Log::warning("Plan de cuentas: la cuenta {$old} se fusionó en {$new}.");
        });
    }

    /**
     * Asigna la cuenta contrapartida a los conceptos de sistema y operativos que aún no la tienen.
     */
    public function assignConceptAccounts(): void
    {
        DB::transaction(function (): void {
            foreach (SystemConcept::cases() as $system) {
                $this->fillAccount($system->value, $system->defaultAccountCode());
            }

            /** @var list<array{code: string, name: string, direction: string, account_code: string}> $catalog */
            $catalog = require database_path('data/finance_concepts.php');

            foreach ($catalog as $row) {
                $existing = FinanceConcept::withTrashed()->where('code', $row['code'])->first();

                if ($existing === null) {
                    $accountId = $this->accountId($row['account_code']);
                    FinanceConcept::create([
                        'code' => $row['code'],
                        'name' => $row['name'],
                        'direction' => ConceptDirection::from($row['direction']),
                        'financial_account_id' => $accountId,
                        'is_system' => false,
                        'is_active' => true,
                    ]);

                    continue;
                }

                $this->fillAccount($row['code'], $row['account_code']);
            }
        });
    }

    private function fillAccount(string $conceptCode, string $accountCode): void
    {
        /** @var FinanceConcept|null $concept */
        $concept = FinanceConcept::where('code', $conceptCode)->first();

        if ($concept === null || $concept->financial_account_id !== null) {
            return;
        }

        $accountId = $this->accountId($accountCode);
        if ($accountId === null) {
            Log::warning("Plan de cuentas: la cuenta {$accountCode} del concepto {$conceptCode} no existe o no está activa.");

            return;
        }

        $concept->update(['financial_account_id' => $accountId]);
    }

    private function accountId(string $code): ?string
    {
        /** @var string|null $id */
        $id = FinancialAccount::query()->active()->postable()->where('code', $code)->value('id');

        return $id;
    }
}
