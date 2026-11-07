<?php

declare(strict_types=1);

namespace App\Modules\Ubigeo\Resources;

use App\Modules\Ubigeo\Data\UbigeoData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UbigeoData
 */
class UbigeoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var UbigeoData $ubigeo */
        $ubigeo = $this->resource;

        return $ubigeo->toArray();
    }
}
