<?php

declare(strict_types=1);

namespace App\Modules\Audit\Models;

use App\Support\Audit\AuditContext;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * @property string $id
 * @property string|null $log_name
 * @property string $description
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $causer_type
 * @property string|null $causer_id
 * @property string|null $branch_id
 * @property string|null $session_id
 * @property string|null $event
 * @property string|null $batch_uuid
 * @property Collection<string, mixed>|null $properties
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Activity extends SpatieActivity
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::saving(function (Activity $activity): void {
            $context = app()->bound(AuditContext::class) ? app(AuditContext::class) : null;

            // branch_id: preferir el de la propia entidad auditada (no requiere contexto de
            // request y funciona igual en requests, jobs y comandos) y usar AuditContext
            // solo como respaldo para subjects sin columna branch_id propia.
            if ($activity->branch_id === null) {
                $activity->branch_id = $activity->subject?->getAttribute('branch_id') ?? $context?->branchId();
            }

            if ($activity->session_id === null) {
                $activity->session_id = $context?->sessionId();
            }
        });
    }
}
