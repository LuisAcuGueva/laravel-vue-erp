<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Providers;

use App\Modules\Maintenance\Models\Area;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Maintenance\Models\Employee;
use App\Support\Branch\ActiveBranch;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class MaintenanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ActiveBranch::class);
    }

    public function boot(): void
    {
        Relation::morphMap([
            'area' => Area::class,
            'branch' => Branch::class,
            'employee' => Employee::class,
        ], merge: true);
    }
}
