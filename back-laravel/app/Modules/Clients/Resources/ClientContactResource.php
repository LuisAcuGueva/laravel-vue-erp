<?php

declare(strict_types=1);

namespace App\Modules\Clients\Resources;

use App\Modules\Clients\Models\ClientContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ClientContact
 */
class ClientContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ClientContact $contact */
        $contact = $this->resource;

        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'position' => $contact->position,
            'phone' => $contact->phone,
            'email' => $contact->email,
            'is_primary' => $contact->is_primary,
        ];
    }
}
