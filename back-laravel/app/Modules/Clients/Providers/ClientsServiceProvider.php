<?php

declare(strict_types=1);

namespace App\Modules\Clients\Providers;

use App\Modules\Clients\Models\Client;
use App\Modules\Clients\Services\ClientService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class ClientsServiceProvider extends ServiceProvider
{
    /**
     * Registra los servicios del módulo.
     */
    public function register(): void
    {
        $this->app->singleton(ClientService::class);
    }

    /**
     * Inicializa los servicios del módulo.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'client' => Client::class,
        ], merge: true);
    }
}
