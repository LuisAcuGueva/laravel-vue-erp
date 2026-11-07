<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Resources;

use App\Modules\Purchases\Models\ProviderContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProviderContact
 */
class ProviderContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProviderContact $contact */
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
