# 007 · audit-trail — Design
> **Spec:** 007 · **Alcance:** fullstack · **Módulo:** Audit · **Prioridad:** media · **Depende de specs:** 001, 002, 003, 005
> **Status:** Draft
> **Última actualización:** 2026-10-14
> **Autores:** Luis Acuña Guevara

---
## 1. Instalación y Adaptación del Modelo Spatie

### 1.1 Dependencia en `back-laravel/composer.json`
```bash
composer require spatie/laravel-activitylog:^4.9
```

### 1.2 Migración Adaptada con UUIDs (`database/migrations/xxxx_create_activity_log_table.php`)
Reemplazando los campos autoincrementales por UUIDs:

```php
Schema::create('activity_log', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('log_name')->nullable()->index();
    $table->text('description');
    $table->nullableUuidMorphs('subject');
    $table->nullableUuidMorphs('causer');
    $table->string('event')->nullable();
    $table->json('properties')->nullable();
    $table->uuid('batch_uuid')->nullable();
    $table->timestamps();
    $table->index('log_name');
});
```

### 1.3 Modelo Extendido: `App\Modules\Audit\Models\Activity.php`
```php
namespace App\Modules\Audit\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'properties' => 'array',
        ]);
    }
}
```

En `config/activitylog.php`:
```php
'activity_model' => \App\Modules\Audit\Models\Activity::class,
```

## 2. Integración en Modelos de Dominio
Uso del trait de Spatie en `Client`, `User`, `Role`:

```php
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Client extends Model
{
    use HasUuids, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
```

## 3. Endpoints de la API
En `routes/api.php`:
```php
Route::prefix('v1/audit-logs')->middleware([JwtAuthenticate::class, 'permission:audit.view'])->group(function () {
    Route::get('/', [AuditLogController::class, 'index']);
    Route::get('/{id}', [AuditLogController::class, 'show'])->whereUuid('id');
});
```

Estructura de respuesta:
```json
{
  "success": true,
  "data": [
    {
      "id": "019277f0-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
      "log_name": "default",
      "description": "updated",
      "subject_type": "App\\Modules\\Clients\\Models\\Client",
      "subject_id": "019277d1-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
      "causer": {
        "id": "019277a0-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
        "name": "Admin Usuario",
        "email": "admin@example.com"
      },
      "properties": {
        "attributes": { "phone": "+34600112233" },
        "old": { "phone": "+34600000000" }
      },
      "created_at": "2026-10-14T18:30:00Z"
    }
  ],
  "meta": { "current_page": 1, "per_page": 15, "total": 1 }
}
```

## 4. Frontend en Vue 3
- Módulo `front-vue/src/modules/audit/`:
  - `stores/auditStore.js`: Consumo de `/api/v1/audit-logs`.
  - `components/AuditHistoryDrawer.vue`: Drawer lateral que renderiza una línea de tiempo (`v-timeline`) con la cronología de cambios de una entidad específica.
  - Página opcional `front-vue/src/pages/audit/index.vue` con visualizador de auditoría global del sistema.
