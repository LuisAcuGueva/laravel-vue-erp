<?php

declare(strict_types=1);

namespace App\Modules\Audit\Resources;

use App\Modules\Audit\Models\Activity;
use App\Modules\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Activity
 */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Activity $activity */
        $activity = $this->resource;

        $causer = $activity->causer;
        $causerData = null;

        if ($causer instanceof User) {
            $causerData = [
                'id' => $causer->id,
                'name' => $causer->name,
                'email' => $causer->email,
            ];
        }

        return [
            'id' => $activity->id,
            'log_name' => $activity->log_name,
            'description' => $activity->description,
            'subject_type' => $activity->subject_type,
            'subject_id' => $activity->subject_id,
            'causer_type' => $activity->causer_type,
            'causer_id' => $activity->causer_id,
            'causer' => $causerData,
            'event' => $activity->event,
            'branch_id' => $activity->branch_id,
            'session_id' => $activity->session_id,
            'properties' => $activity->properties?->toArray() ?? [],
            'created_at' => $activity->created_at?->toIso8601String(),
        ];
    }
}
