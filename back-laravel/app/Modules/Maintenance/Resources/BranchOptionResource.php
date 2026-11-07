<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Resources;

use App\Modules\Maintenance\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Branch
 */
class BranchOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Branch $branch */
        $branch = $this->resource;

        return [
            'id' => $branch->id,
            'code' => $branch->code,
            'name' => $branch->name,
        ];
    }
}
