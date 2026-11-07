<?php

declare(strict_types=1);

namespace App\Modules\Ubigeo\Providers;

use App\Modules\Ubigeo\Services\UbigeoService;
use Illuminate\Support\ServiceProvider;

class UbigeoServiceProvider extends ServiceProvider
{
    /**
     * Registra los servicios del módulo.
     */
    public function register(): void
    {
        $this->app->singleton(UbigeoService::class);
    }

    /**
     * Inicializa los servicios del módulo.
     */
    public function boot(): void {}
}
