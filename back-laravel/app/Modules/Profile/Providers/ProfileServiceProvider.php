<?php

declare(strict_types=1);

namespace App\Modules\Profile\Providers;

use App\Modules\Profile\Services\ProfileService;
use Illuminate\Support\ServiceProvider;

class ProfileServiceProvider extends ServiceProvider
{
    /**
     * Registra los servicios del módulo.
     */
    public function register(): void
    {
        $this->app->singleton(ProfileService::class);
    }

    /**
     * Inicializa los servicios del módulo.
     */
    public function boot(): void
    {
        // Espacio para registrar bindings, eventos o observers específicos del módulo
    }
}
