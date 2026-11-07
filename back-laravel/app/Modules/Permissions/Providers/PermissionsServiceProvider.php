<?php

declare(strict_types=1);

namespace App\Modules\Permissions\Providers;

use App\Modules\Permissions\Models\Permission;
use App\Modules\Permissions\Services\PermissionService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class PermissionsServiceProvider extends ServiceProvider
{
    /**
     * Registra los servicios del módulo.
     */
    public function register(): void
    {
        $this->app->singleton(PermissionService::class);
    }

    /**
     * Inicializa los servicios del módulo.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'permission' => Permission::class,
        ], merge: true);
    }
}
