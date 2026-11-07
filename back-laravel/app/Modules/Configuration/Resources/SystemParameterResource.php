<?php

declare(strict_types=1);

namespace App\Modules\Configuration\Resources;

use App\Modules\Configuration\Models\SystemParameter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SystemParameter
 */
class SystemParameterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SystemParameter $param */
        $param = $this->resource;

        return [
            'id' => $param->id,
            'group_key' => $param->group_key,
            'item_key' => $param->item_key,
            'key' => "{$param->group_key}.{$param->item_key}",
            'label' => $param->label,
            'description' => $param->description,
            'value_type' => $param->value_type->value,
            'value' => $param->value_type->cast($param->value),
            'is_editable' => (bool) $param->is_editable,
            'is_active' => (bool) $param->is_active,
            'created_at' => $param->created_at?->toIso8601String(),
            'updated_at' => $param->updated_at?->toIso8601String(),
        ];
    }
}
