<?php

declare(strict_types=1);

namespace App\Modules\Finance\Resources;

use App\Modules\Finance\Models\Box;
use App\Modules\Finance\Models\CashSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Box
 */
class BoxResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $openSessionData = null;
        if ($this->relationLoaded('openSession') && $this->openSession instanceof CashSession) {
            $openSessionData = [
                'id' => $this->openSession->id,
                'number' => $this->openSession->number,
                'status' => $this->openSession->status instanceof \BackedEnum
                    ? $this->openSession->status->value
                    : (string) $this->openSession->status,
                'opened_at' => $this->openSession->opened_at?->toIso8601String(),
                'opened_by' => $this->openSession->opened_by,
                'opening_amount' => $this->openSession->opening_amount,
                'opener' => $this->openSession->relationLoaded('opener') && $this->openSession->opener !== null ? [
                    'id' => $this->openSession->opener->id,
                    'name' => $this->openSession->opener->name,
                ] : null,
            ];
        }

        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch', fn () => $this->branch !== null ? [
                'id' => $this->branch->id,
                'code' => $this->branch->code,
                'name' => $this->branch->name,
            ] : null),
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn ($r): array => [
                'id' => $r->id,
                'name' => $r->name,
            ])),
            'role_ids' => $this->whenLoaded('roles', fn () => $this->roles->pluck('id')->values()->all()),
            'open_session' => $openSessionData,
            'current_session' => $openSessionData,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
