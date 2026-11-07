<?php

declare(strict_types=1);

namespace App\Modules\Configuration\Providers;

use App\Modules\Configuration\Models\CompanySetting;
use App\Modules\Configuration\Models\SystemParameter;
use App\Modules\Configuration\Services\CompanySettingService;
use App\Modules\Configuration\Services\SystemParameterService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class ConfigurationServiceProvider extends ServiceProvider
{
    /**
     * Registra los servicios del módulo.
     */
    public function register(): void
    {
        $this->app->singleton(SystemParameterService::class);
        $this->app->singleton(CompanySettingService::class);
    }

    /**
     * Inicializa los servicios del módulo.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'company_setting' => CompanySetting::class,
            'system_parameter' => SystemParameter::class,
        ], merge: true);
    }
}
