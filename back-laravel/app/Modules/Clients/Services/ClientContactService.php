<?php

declare(strict_types=1);

namespace App\Modules\Clients\Services;

use App\Modules\Clients\Exceptions\ClientRuleException;
use App\Modules\Clients\Models\Client;
use App\Modules\Clients\Models\ClientContact;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ClientContactService
{
    /**
     * Sincroniza la lista completa de contactos: actualiza por `id`, crea los nuevos y elimina los ausentes.
     *
     * @param  list<array<string, mixed>>  $contacts
     * @return Collection<int, ClientContact>
     *
     * @throws ClientRuleException
     */
    public function sync(Client $client, array $contacts): Collection
    {
        if (count(array_filter($contacts, fn (array $c): bool => (bool) ($c['is_primary'] ?? false))) > 1) {
            throw new ClientRuleException('Solo puede haber un contacto principal', 'contacts');
        }

        return DB::transaction(function () use ($client, $contacts): Collection {
            $existing = $client->contacts()->get()->keyBy('id');
            $keep = [];

            foreach ($contacts as $index => $data) {
                $id = $data['id'] ?? null;

                if ($id !== null && ! $existing->has($id)) {
                    throw new ClientRuleException('El contacto no pertenece al cliente', "contacts.{$index}.id");
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

            ClientContact::query()->where('client_id', $client->id)->whereNotIn('id', $keep)->delete();

            foreach ($contacts as $data) {
                if (($data['id'] ?? null) === null) {
                    $client->contacts()->create([
                        'name' => $data['name'],
                        'position' => $data['position'] ?? null,
                        'phone' => $data['phone'] ?? null,
                        'email' => $data['email'] ?? null,
                        'is_primary' => (bool) ($data['is_primary'] ?? false),
                    ]);
                }
            }

            return $client->contacts()->get();
        });
    }
}
