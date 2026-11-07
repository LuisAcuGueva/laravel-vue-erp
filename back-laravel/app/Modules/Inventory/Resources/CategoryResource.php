<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use App\Modules\Inventory\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Category $category */
        $category = $this->resource;

        return [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'code' => $category->code,
            'name' => $category->name,
            'description' => $category->description,
            'depth' => $category->depth,
            'path' => $category->path(),
            'is_active' => $category->is_active,
            'parent' => $this->whenLoaded('parent', fn () => $category->parent !== null ? [
                'id' => $category->parent->id,
                'name' => $category->parent->name,
                'code' => $category->parent->code,
            ] : null),
            'children_count' => $this->whenCounted('children'),
            'created_at' => $category->created_at?->toIso8601String(),
            'updated_at' => $category->updated_at?->toIso8601String(),
        ];
    }
}
