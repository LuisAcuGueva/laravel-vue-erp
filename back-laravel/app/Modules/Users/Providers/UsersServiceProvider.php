<?php

declare(strict_types=1);

namespace App\Modules\Users\Providers;

use App\Modules\Users\Models\User;
use App\Modules\Users\Services\UserService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class UsersServiceProvider extends ServiceProvider
{
    /**
     * Registra los servicios del módulo.
     */
    public function register(): void
    {
        $this->app->singleton(UserService::class);
    }

    /**
     * Inicializa los servicios del módulo.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'user' => User::class,
        ], merge: true);
    }
}
