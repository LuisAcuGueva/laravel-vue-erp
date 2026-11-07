<?php

declare(strict_types=1);

namespace App\Modules\Audit\Concerns;

use Spatie\Activitylog\Models\Activity;

/**
 * Permite marcar una mutación puntual como originada por un proceso del sistema
 * (sin causer humano, p. ej. la respuesta de un proveedor externo en un job en cola)
 * en vez de dejar `causer_type`/`causer_id` en NULL de forma indistinguible de un bug.
 *
 * Uso: antes de guardar el modelo, setear `$model->loggedAsSystemAction = true;`.
 * Se usa junto con `Spatie\Activitylog\Traits\LogsActivity`.
 */
trait LogsSystemActivity
{
    public bool $loggedAsSystemAction = false;

    public function tapActivity(Activity $activity, string $eventName): void
    {
        if (! $this->loggedAsSystemAction) {
            return;
        }

        $properties = $activity->properties ?? collect();
        $activity->properties = $properties->put('system', true);
    }
}
