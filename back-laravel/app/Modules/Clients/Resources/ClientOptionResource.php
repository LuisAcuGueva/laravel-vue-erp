<?php

declare(strict_types=1);

namespace App\Modules\Clients\Resources;

use App\Modules\Clients\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Client
 */
class ClientOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Client $client */
        $client = $this->resource;

        return [
            'id' => $client->id,
            'client_type' => $client->client_type->value,
            'document_type' => $client->document_type?->value,
            'document_number' => $client->document_number,
            'name' => $client->name,
            'address' => $client->address,
            'default_price_type' => $client->default_price_type->value,
            'credit_limit' => (float) $client->credit_limit,
            'is_system' => $client->is_system,
        ];
    }
}
