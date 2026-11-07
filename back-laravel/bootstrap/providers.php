<?php

use App\Modules\Clients\Providers\ClientsServiceProvider;
use App\Modules\Configuration\Providers\ConfigurationServiceProvider;
use App\Modules\Finance\Providers\FinanceServiceProvider;
use App\Modules\Inventory\Providers\InventoryServiceProvider;
use App\Modules\Maintenance\Providers\MaintenanceServiceProvider;
use App\Modules\Permissions\Providers\PermissionsServiceProvider;
use App\Modules\Profile\Providers\ProfileServiceProvider;
use App\Modules\Purchases\Providers\PurchasesServiceProvider;
use App\Modules\Roles\Providers\RolesServiceProvider;
use App\Modules\Sales\Providers\SalesServiceProvider;
use App\Modules\Ubigeo\Providers\UbigeoServiceProvider;
use App\Modules\Users\Providers\UsersServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    ClientsServiceProvider::class,
    ConfigurationServiceProvider::class,
    FinanceServiceProvider::class,
    InventoryServiceProvider::class,
    MaintenanceServiceProvider::class,
    PermissionsServiceProvider::class,
    RolesServiceProvider::class,
    UsersServiceProvider::class,
    ProfileServiceProvider::class,
    PurchasesServiceProvider::class,
    SalesServiceProvider::class,
    UbigeoServiceProvider::class,
];
