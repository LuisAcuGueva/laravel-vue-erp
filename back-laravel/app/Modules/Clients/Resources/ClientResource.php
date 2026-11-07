<?php

declare(strict_types=1);

namespace App\Modules\Clients\Resources;

use App\Modules\Clients\Models\Client;
use App\Modules\Sales\Services\CreditPolicyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Client
 */
class ClientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Client $client */
        $client = $this->resource;

        $debt = (float) app(CreditPolicyService::class)->debt($client);
        $creditLimit = (float) $client->credit_limit;
        $availableCredit = max(0.0, round($creditLimit - $debt, 2));

        return [
            'id' => $client->id,
            'client_type' => $client->client_type->value,
            'document_type' => $client->document_type?->value,
            'document_number' => $client->document_number,
            'name' => $client->name,
            'trade_name' => $client->trade_name,
            'representative_name' => $client->representative_name,
            'representative_document' => $client->representative_document,
            'email' => $client->email,
            'phone' => $client->phone,
            'address' => $client->address,
            'ubigeo' => $client->ubigeo,
            'default_price_type' => $client->default_price_type->value,
            'credit_limit' => $creditLimit,
            'credit_days' => $client->credit_days,
            'is_system' => $client->is_system,
            'is_active' => $client->is_active,
            'description' => $client->description,
            'contacts' => ClientContactResource::collection($this->whenLoaded('contacts')),
            'account' => [
                'debt' => $debt,
                'available_credit' => $availableCredit,
            ],
            'created_at' => $client->created_at?->toIso8601String(),
            'updated_at' => $client->updated_at?->toIso8601String(),
        ];
    }
}
