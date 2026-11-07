<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fila de antigüedad de saldos por proveedor y moneda (montos como string con 2 decimales).
 */
class AgingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $row */
        $row = $this->resource;

        return [
            'provider' => $row['provider'],
            'currency' => $row['currency'],
            'current' => $row['current'],
            'd1_30' => $row['d1_30'],
            'd31_60' => $row['d31_60'],
            'd61_90' => $row['d61_90'],
            'over_90' => $row['over_90'],
            'total' => $row['total'],
        ];
    }
}
