<?php

declare(strict_types=1);

namespace App\Modules\Finance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankAccountSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $res */
        $res = is_array($this->resource) ? $this->resource : (array) parent::toArray($request);

        return $res;
    }
}
