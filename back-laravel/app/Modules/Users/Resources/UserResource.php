<?php

declare(strict_types=1);

namespace App\Modules\Users\Resources;

use App\Modules\Maintenance\Resources\UserBranchResource;
use App\Modules\Users\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'roles' => $this->roles->pluck('name')->sort()->values()->all(),
            'branches' => UserBranchResource::collection($this->whenLoaded('branches')),
            'last_login_at' => $this->last_login_at instanceof CarbonInterface ? $this->last_login_at->toIso8601String() : $this->last_login_at,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
