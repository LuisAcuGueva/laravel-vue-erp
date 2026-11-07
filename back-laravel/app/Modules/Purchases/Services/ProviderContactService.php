<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Services;

use App\Modules\Purchases\Exceptions\PurchasesRuleException;
use App\Modules\Purchases\Models\Provider;
use App\Modules\Purchases\Models\ProviderContact;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProviderContactService
{
    /**
     * Sincroniza la lista completa de contactos: actualiza por `id`, crea los nuevos y elimina los ausentes.
     *
     * @param  list<array<string, mixed>>  $contacts
     * @return Collection<int, ProviderContact>
     *
     * @throws PurchasesRuleException
     */
    public function sync(Provider $provider, array $contacts): Collection
    {
        if (count(array_filter($contacts, fn (array $c): bool => (bool) ($c['is_primary'] ?? false))) > 1) {
            throw new PurchasesRuleException('Solo puede haber un contacto principal', 'contacts');
        }

        return DB::transaction(function () use ($provider, $contacts): Collection {
            $existing = $provider->contacts()->get()->keyBy('id');
            $keep = [];

            foreach ($contacts as $index => $data) {
                $id = $data['id'] ?? null;

                if ($id !== null && ! $existing->has($id)) {
                    throw new PurchasesRuleException('El contacto no pertenece al proveedor', "contacts.{$index}.id");
                }

                $attributes = [
                    'name' => $data['name'],
                    'position' => $data['position'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'is_primary' => (bool) ($data['is_primary'] ?? false),
                ];

                if ($id !== null) {
                    $existing->get($id)?->update($attributes);
                    $keep[] = $id;
                }
            }

            ProviderContact::query()->where('provider_id', $provider->id)->whereNotIn('id', $keep)->delete();

            foreach ($contacts as $data) {
                if (($data['id'] ?? null) === null) {
                    $provider->contacts()->create([
                        'name' => $data['name'],
                        'position' => $data['position'] ?? null,
                        'phone' => $data['phone'] ?? null,
                        'email' => $data['email'] ?? null,
                        'is_primary' => (bool) ($data['is_primary'] ?? false),
                    ]);
                }
            }

            return $provider->contacts()->get();
        });
    }
}
