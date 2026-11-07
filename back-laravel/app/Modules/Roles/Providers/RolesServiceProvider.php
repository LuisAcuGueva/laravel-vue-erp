<?php

declare(strict_types=1);

namespace App\Modules\Roles\Providers;

use App\Modules\Roles\Models\Role;
use App\Modules\Roles\Services\RoleService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class RolesServiceProvider extends ServiceProvider
{
    /**
     * Registra los servicios del módulo.
     */
    public function register(): void
    {
        $this->app->singleton(RoleService::class);
    }

    /**
     * Inicializa los servicios del módulo.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'role' => Role::class,
        ], merge: true);
    }
}
