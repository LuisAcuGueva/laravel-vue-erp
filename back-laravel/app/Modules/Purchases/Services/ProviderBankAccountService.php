<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Services;

use App\Modules\Purchases\Exceptions\PurchasesRuleException;
use App\Modules\Purchases\Models\Provider;
use App\Modules\Purchases\Models\ProviderBankAccount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProviderBankAccountService
{
    /**
     * Sincroniza la lista completa de cuentas: actualiza por `id`, crea las nuevas y elimina las ausentes.
     *
     * @param  list<array<string, mixed>>  $accounts
     * @return Collection<int, ProviderBankAccount>
     *
     * @throws PurchasesRuleException
     */
    public function sync(Provider $provider, array $accounts): Collection
    {
        $seen = [];
        foreach ($accounts as $index => $data) {
            $key = $data['bank_id'].'|'.mb_strtolower(trim((string) $data['account_number']));
            if (isset($seen[$key])) {
                throw new PurchasesRuleException('El número de cuenta está repetido para el mismo banco', "accounts.{$index}.account_number");
            }
            $seen[$key] = true;
        }

        return DB::transaction(function () use ($provider, $accounts): Collection {
            $existing = $provider->bankAccounts()->get()->keyBy('id');

            foreach ($accounts as $index => $data) {
                $id = $data['id'] ?? null;
                if ($id !== null && ! $existing->has($id)) {
                    throw new PurchasesRuleException('La cuenta no pertenece al proveedor', "accounts.{$index}.id");
                }
            }

            $keep = array_values(array_filter(array_column($accounts, 'id')));
            ProviderBankAccount::query()->where('provider_id', $provider->id)->whereNotIn('id', $keep)->delete();

            foreach ($accounts as $data) {
                $attributes = [
                    'bank_id' => $data['bank_id'],
                    'currency_id' => $data['currency_id'],
                    'account_number' => trim((string) $data['account_number']),
                    'cci' => $data['cci'] ?? null,
                    'is_detraction_account' => (bool) ($data['is_detraction_account'] ?? false),
                ];

                if (($data['id'] ?? null) !== null) {
                    $existing->get($data['id'])?->update($attributes);
                } else {
                    $provider->bankAccounts()->create($attributes);
                }
            }

            return $provider->bankAccounts()->with(['bank:id,name,short_name', 'currency:id,code,symbol'])->get();
        });
    }
}
