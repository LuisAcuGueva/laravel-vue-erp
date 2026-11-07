<?php

use App\Modules\Audit\Controllers\AuditLogController;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Auth\Middleware\JwtAuthenticate;
use App\Modules\Clients\Controllers\ClientContactController;
use App\Modules\Clients\Controllers\ClientController;
use App\Modules\Configuration\Controllers\CompanySettingController;
use App\Modules\Configuration\Controllers\SystemParameterController;
use App\Modules\Finance\Controllers\BankAccountController;
use App\Modules\Finance\Controllers\BankAccountSummaryController;
use App\Modules\Finance\Controllers\BankController;
use App\Modules\Finance\Controllers\BankMovementController;
use App\Modules\Finance\Controllers\BankOperationController;
use App\Modules\Finance\Controllers\BankPeriodController;
use App\Modules\Finance\Controllers\BoxController;
use App\Modules\Finance\Controllers\CashMovementController;
use App\Modules\Finance\Controllers\CashSessionController;
use App\Modules\Finance\Controllers\CashTransferController;
use App\Modules\Finance\Controllers\CurrencyController;
use App\Modules\Finance\Controllers\ExchangeRateController;
use App\Modules\Finance\Controllers\FinanceConceptController;
use App\Modules\Finance\Controllers\FinanceSettingsHealthController;
use App\Modules\Finance\Controllers\FinancialAccountController;
use App\Modules\Finance\Controllers\PaymentMethodController;
use App\Modules\Inventory\Controllers\BrandController;
use App\Modules\Inventory\Controllers\CatalogItemController;
use App\Modules\Inventory\Controllers\CategoryController;
use App\Modules\Inventory\Controllers\InventoryCountController;
use App\Modules\Inventory\Controllers\ItemPresentationController;
use App\Modules\Inventory\Controllers\ItemPriceController;
use App\Modules\Inventory\Controllers\KardexController;
use App\Modules\Inventory\Controllers\StockController;
use App\Modules\Inventory\Controllers\UnitCategoryController;
use App\Modules\Inventory\Controllers\UnitController;
use App\Modules\Inventory\Controllers\WarehouseController;
use App\Modules\Inventory\Controllers\WarehouseNoteController;
use App\Modules\Inventory\Controllers\WarehouseReasonController;
use App\Modules\Maintenance\Controllers\AreaController;
use App\Modules\Maintenance\Controllers\BranchController;
use App\Modules\Maintenance\Controllers\EmployeeController;
use App\Modules\Maintenance\Controllers\EmployeeUserController;
use App\Modules\Maintenance\Controllers\UserBranchController;
use App\Modules\Permissions\Controllers\PermissionController;
use App\Modules\Profile\Controllers\ProfileController;
use App\Modules\Purchases\Controllers\AccountsPayableController;
use App\Modules\Purchases\Controllers\ProviderBankAccountController;
use App\Modules\Purchases\Controllers\ProviderContactController;
use App\Modules\Purchases\Controllers\ProviderController;
use App\Modules\Purchases\Controllers\ProviderPriceController;
use App\Modules\Purchases\Controllers\PurchaseOrderController;
use App\Modules\Purchases\Controllers\PurchaseReceiptController;
use App\Modules\Purchases\Controllers\PurchaseRequestController;
use App\Modules\Purchases\Controllers\SupplierInvoiceController;
use App\Modules\Purchases\Controllers\SupplierPaymentController;
use App\Modules\Roles\Controllers\RoleController;
use App\Modules\Sales\Controllers\ClientStatementController;
use App\Modules\Sales\Controllers\CollectionController;
use App\Modules\Sales\Controllers\CreditNoteController;
use App\Modules\Sales\Controllers\DocumentSeriesController;
use App\Modules\Sales\Controllers\QuotationController;
use App\Modules\Sales\Controllers\ReceivableController;
use App\Modules\Sales\Controllers\SaleController;
use App\Modules\Ubigeo\Controllers\UbigeoController;
use App\Modules\Users\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware(JwtAuthenticate::class)->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
    });
});

// Rutas API v1 protegidas con Rate Limiting general
Route::prefix('v1')->middleware(['throttle:api', JwtAuthenticate::class])->group(function () {
    // Módulo Clients (Spec 025 — autorización por permiso)
    Route::prefix('clients')->group(function () {
        Route::get('/options', [ClientController::class, 'options'])->middleware('permission:clients.view');
        Route::get('/export', [ClientController::class, 'export'])->middleware('permission:clients.export');
        Route::get('/', [ClientController::class, 'index'])->middleware('permission:clients.view');
        Route::post('/', [ClientController::class, 'store'])->middleware('permission:clients.create');
        Route::get('/{id}', [ClientController::class, 'show'])->whereUuid('id')->middleware('permission:clients.view');
        Route::match(['put', 'patch'], '/{id}', [ClientController::class, 'update'])->whereUuid('id')->middleware('permission:clients.update');
        Route::delete('/{id}', [ClientController::class, 'destroy'])->whereUuid('id')->middleware('permission:clients.delete');
        Route::put('/{id}/credit', [ClientController::class, 'updateCredit'])->whereUuid('id')->middleware('permission:clients.manage_credit');
        Route::put('/{id}/contacts', [ClientContactController::class, 'sync'])->whereUuid('id')->middleware('permission:clients.update');
        Route::get('/{id}/statement', [ClientStatementController::class, 'show'])->whereUuid('id')->middleware('permission:receivables.view');
        Route::get('/{id}/statement/pdf', [ClientStatementController::class, 'pdf'])->whereUuid('id')->middleware('permission:receivables.view');
    });

    // Módulo Permissions
    Route::prefix('permissions')->group(function () {
        Route::get('/', [PermissionController::class, 'index'])->middleware('permission:permissions.view');
        Route::post('/', [PermissionController::class, 'store'])->middleware('permission:permissions.create');
        Route::get('/{id}', [PermissionController::class, 'show'])->whereUuid('id')->middleware('permission:permissions.view');
        Route::match(['put', 'patch'], '/{id}', [PermissionController::class, 'update'])->whereUuid('id')->middleware('permission:permissions.update');
        Route::delete('/{id}', [PermissionController::class, 'destroy'])->whereUuid('id')->middleware('permission:permissions.delete');
    });

    // Módulo Roles
    Route::prefix('roles')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->middleware('permission:roles.view');
        Route::post('/', [RoleController::class, 'store'])->middleware('permission:roles.create');
        Route::get('/{id}', [RoleController::class, 'show'])->whereUuid('id')->middleware('permission:roles.view');
        Route::match(['put', 'patch'], '/{id}', [RoleController::class, 'update'])->whereUuid('id')->middleware('permission:roles.update');
        Route::delete('/{id}', [RoleController::class, 'destroy'])->whereUuid('id')->middleware('permission:roles.delete');
    });

    // Módulo Users
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index'])->middleware('permission:users.view');
        Route::get('/export', [UserController::class, 'export'])->middleware('permission:users.export');
        Route::post('/', [UserController::class, 'store'])->middleware('permission:users.create');
        Route::get('/{id}', [UserController::class, 'show'])->whereUuid('id')->middleware('permission:users.view');
        Route::match(['put', 'patch'], '/{id}', [UserController::class, 'update'])->whereUuid('id')->middleware('permission:users.update');
        Route::delete('/{id}', [UserController::class, 'destroy'])->whereUuid('id')->middleware('permission:users.delete');
        Route::put('/{id}/branches', [UserBranchController::class, 'update'])->whereUuid('id')->middleware('permission:users.update');
    });

    // Módulo Audit (Spec 007)
    Route::prefix('audit-logs')->group(function () {
        Route::get('/', [AuditLogController::class, 'index'])->middleware('permission:audit.view');
        Route::get('/subject-types', [AuditLogController::class, 'subjectTypes'])->middleware('permission:audit.view');
        Route::get('/{id}', [AuditLogController::class, 'show'])->whereUuid('id')->middleware('permission:audit.view');
    });

    // Módulo Profile (Spec 009)
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::match(['put', 'patch'], '/', [ProfileController::class, 'update']);
        Route::put('/password', [ProfileController::class, 'updatePassword']);
        Route::get('/sessions', [ProfileController::class, 'listSessions']);
        Route::delete('/sessions/others', [ProfileController::class, 'revokeOtherSessions']);
    });

    // Módulo Configuration (Spec 010)
    Route::prefix('configuration')->group(function () {
        Route::get('/company/summary', [CompanySettingController::class, 'summary']);
        Route::get('/company', [CompanySettingController::class, 'show'])->middleware('permission:configuration.view');
        Route::put('/company', [CompanySettingController::class, 'update'])->middleware('permission:configuration.update');
        Route::post('/company/logo', [CompanySettingController::class, 'uploadLogo'])->middleware('permission:configuration.update');
        Route::get('/parameters', [SystemParameterController::class, 'index'])->middleware('permission:configuration.view');
        Route::put('/parameters/{id}', [SystemParameterController::class, 'update'])->whereUuid('id')->middleware('permission:configuration.update');
    });

    // Módulo Ubigeo — catálogo INEI de solo lectura (Spec 030)
    Route::get('/ubigeo', [UbigeoController::class, 'index']);

    // Módulo Maintenance — Sedes y Áreas (Spec 011)
    Route::prefix('branches')->group(function () {
        Route::get('/options', [BranchController::class, 'options']);
        Route::get('/', [BranchController::class, 'index'])->middleware('permission:branches.view');
        Route::get('/export', [BranchController::class, 'export'])->middleware('permission:branches.export');
        Route::post('/', [BranchController::class, 'store'])->middleware('permission:branches.create');
        Route::get('/{id}', [BranchController::class, 'show'])->whereUuid('id')->middleware('permission:branches.view');
        Route::match(['put', 'patch'], '/{id}', [BranchController::class, 'update'])->whereUuid('id')->middleware('permission:branches.update');
        Route::delete('/{id}', [BranchController::class, 'destroy'])->whereUuid('id')->middleware('permission:branches.delete');
    });

    Route::prefix('areas')->group(function () {
        Route::get('/options', [AreaController::class, 'options']);
        Route::get('/', [AreaController::class, 'index'])->middleware('permission:areas.view');
        Route::get('/export', [AreaController::class, 'export'])->middleware('permission:areas.export');
        Route::post('/', [AreaController::class, 'store'])->middleware('permission:areas.create');
        Route::get('/{id}', [AreaController::class, 'show'])->whereUuid('id')->middleware('permission:areas.view');
        Route::match(['put', 'patch'], '/{id}', [AreaController::class, 'update'])->whereUuid('id')->middleware('permission:areas.update');
        Route::delete('/{id}', [AreaController::class, 'destroy'])->whereUuid('id')->middleware('permission:areas.delete');
    });

    // Módulo Maintenance — Empleados (Spec 012)
    Route::prefix('employees')->group(function () {
        Route::get('/options', [EmployeeController::class, 'options']);
        Route::get('/', [EmployeeController::class, 'index'])->middleware('permission:employees.view');
        Route::get('/export', [EmployeeController::class, 'export'])->middleware('permission:employees.export');
        Route::post('/', [EmployeeController::class, 'store'])->middleware('permission:employees.create');
        Route::get('/{id}', [EmployeeController::class, 'show'])->whereUuid('id')->middleware('permission:employees.view');
        Route::match(['put', 'patch'], '/{id}', [EmployeeController::class, 'update'])->whereUuid('id')->middleware('permission:employees.update');
        Route::delete('/{id}', [EmployeeController::class, 'destroy'])->whereUuid('id')->middleware('permission:employees.delete');
        Route::post('/{id}/user', [EmployeeUserController::class, 'store'])->whereUuid('id')->middleware(['permission:employees.update', 'permission:users.create']);
        Route::put('/{id}/user', [EmployeeUserController::class, 'update'])->whereUuid('id')->middleware('permission:employees.update');
        Route::delete('/{id}/user', [EmployeeUserController::class, 'destroy'])->whereUuid('id')->middleware('permission:employees.update');
    });

    // Módulo Inventory — Maestros (Spec 013)
    Route::prefix('inventory')->group(function () {
        // Categorías
        Route::prefix('categories')->group(function () {
            Route::get('/tree', [CategoryController::class, 'tree']);
            Route::get('/options', [CategoryController::class, 'options']);
            Route::get('/export', [CategoryController::class, 'export'])->middleware('permission:categories.export');
            Route::get('/', [CategoryController::class, 'index'])->middleware('permission:categories.view');
            Route::post('/', [CategoryController::class, 'store'])->middleware('permission:categories.create');
            Route::get('/{id}', [CategoryController::class, 'show'])->whereUuid('id')->middleware('permission:categories.view');
            Route::match(['put', 'patch'], '/{id}', [CategoryController::class, 'update'])->whereUuid('id')->middleware('permission:categories.update');
            Route::delete('/{id}', [CategoryController::class, 'destroy'])->whereUuid('id')->middleware('permission:categories.delete');
        });

        // Marcas
        Route::prefix('brands')->group(function () {
            Route::get('/options', [BrandController::class, 'options']);
            Route::get('/export', [BrandController::class, 'export'])->middleware('permission:brands.export');
            Route::get('/', [BrandController::class, 'index'])->middleware('permission:brands.view');
            Route::post('/', [BrandController::class, 'store'])->middleware('permission:brands.create');
            Route::get('/{id}', [BrandController::class, 'show'])->whereUuid('id')->middleware('permission:brands.view');
            Route::match(['put', 'patch'], '/{id}', [BrandController::class, 'update'])->whereUuid('id')->middleware('permission:brands.update');
            Route::delete('/{id}', [BrandController::class, 'destroy'])->whereUuid('id')->middleware('permission:brands.delete');
        });

        // Categorías de unidades
        Route::prefix('unit-categories')->group(function () {
            Route::get('/options', [UnitCategoryController::class, 'options']);
            Route::get('/', [UnitCategoryController::class, 'index'])->middleware('permission:units.view');
            Route::post('/', [UnitCategoryController::class, 'store'])->middleware('permission:units.create');
            Route::get('/{id}', [UnitCategoryController::class, 'show'])->whereUuid('id')->middleware('permission:units.view');
            Route::match(['put', 'patch'], '/{id}', [UnitCategoryController::class, 'update'])->whereUuid('id')->middleware('permission:units.update');
            Route::delete('/{id}', [UnitCategoryController::class, 'destroy'])->whereUuid('id')->middleware('permission:units.delete');
        });

        // Unidades
        Route::prefix('units')->group(function () {
            Route::post('/convert', [UnitController::class, 'convert']);
            Route::get('/options', [UnitController::class, 'options']);
            Route::get('/export', [UnitController::class, 'export'])->middleware('permission:units.export');
            Route::get('/', [UnitController::class, 'index'])->middleware('permission:units.view');
            Route::post('/', [UnitController::class, 'store'])->middleware('permission:units.create');
            Route::get('/{id}', [UnitController::class, 'show'])->whereUuid('id')->middleware('permission:units.view');
            Route::match(['put', 'patch'], '/{id}', [UnitController::class, 'update'])->whereUuid('id')->middleware('permission:units.update');
            Route::delete('/{id}', [UnitController::class, 'destroy'])->whereUuid('id')->middleware('permission:units.delete');
        });

        // Almacenes
        Route::prefix('warehouses')->group(function () {
            Route::get('/options', [WarehouseController::class, 'options'])->middleware('branch');
            Route::get('/export', [WarehouseController::class, 'export'])->middleware('permission:warehouses.export');
            Route::get('/', [WarehouseController::class, 'index'])->middleware('permission:warehouses.view');
            Route::post('/', [WarehouseController::class, 'store'])->middleware('permission:warehouses.create');
            Route::get('/{id}', [WarehouseController::class, 'show'])->whereUuid('id')->middleware('permission:warehouses.view');
            Route::match(['put', 'patch'], '/{id}', [WarehouseController::class, 'update'])->whereUuid('id')->middleware('permission:warehouses.update');
            Route::delete('/{id}', [WarehouseController::class, 'destroy'])->whereUuid('id')->middleware('permission:warehouses.delete');
        });

        // Motivos de movimiento de almacén
        Route::prefix('warehouse-reasons')->group(function () {
            Route::get('/options', [WarehouseReasonController::class, 'options']);
            Route::get('/export', [WarehouseReasonController::class, 'export'])->middleware('permission:warehouse_reasons.export');
            Route::get('/', [WarehouseReasonController::class, 'index'])->middleware('permission:warehouse_reasons.view');
            Route::post('/', [WarehouseReasonController::class, 'store'])->middleware('permission:warehouse_reasons.create');
            Route::get('/{id}', [WarehouseReasonController::class, 'show'])->whereUuid('id')->middleware('permission:warehouse_reasons.view');
            Route::match(['put', 'patch'], '/{id}', [WarehouseReasonController::class, 'update'])->whereUuid('id')->middleware('permission:warehouse_reasons.update');
            Route::delete('/{id}', [WarehouseReasonController::class, 'destroy'])->whereUuid('id')->middleware('permission:warehouse_reasons.delete');
        });

        // Catálogo maestro (Spec 014)
        Route::get('/catalog-items/lookup', [CatalogItemController::class, 'lookup'])->middleware('permission:catalog.view');
        Route::get('/catalog-items', [CatalogItemController::class, 'index'])->middleware('permission:catalog.view');
        Route::post('/catalog-items', [CatalogItemController::class, 'store'])->middleware('permission:catalog.create');
        Route::get('/catalog-items/{id}', [CatalogItemController::class, 'show'])->whereUuid('id')->middleware('permission:catalog.view');
        Route::match(['put', 'patch'], '/catalog-items/{id}', [CatalogItemController::class, 'update'])->whereUuid('id')->middleware('permission:catalog.update');
        Route::delete('/catalog-items/{id}', [CatalogItemController::class, 'destroy'])->whereUuid('id')->middleware('permission:catalog.delete');
        Route::get('/catalog-items/{id}/presentations', [ItemPresentationController::class, 'index'])->whereUuid('id')->middleware('permission:catalog.view');
        Route::post('/catalog-items/{id}/presentations', [ItemPresentationController::class, 'store'])->whereUuid('id')->middleware('permission:catalog.update');
        Route::put('/catalog-items/{id}/presentations/{presentationId}', [ItemPresentationController::class, 'update'])->whereUuid(['id', 'presentationId'])->middleware('permission:catalog.update');
        Route::delete('/catalog-items/{id}/presentations/{presentationId}', [ItemPresentationController::class, 'destroy'])->whereUuid(['id', 'presentationId'])->middleware('permission:catalog.update');
        Route::put('/catalog-items/{id}/prices', [ItemPriceController::class, 'sync'])->whereUuid('id')->middleware('permission:catalog.update_prices');

        // Stock y Kardex (Spec 015)
        Route::middleware('branch')->group(function () {
            Route::get('/stock', [StockController::class, 'index'])->middleware('permission:stock.view');
            Route::get('/stock/by-item/{id}', [StockController::class, 'byItem'])->whereUuid('id')->middleware('permission:stock.view');
            Route::put('/stock/limits', [StockController::class, 'updateLimits'])->middleware('permission:stock.update_limits');
            Route::get('/kardex', [KardexController::class, 'index'])->middleware('permission:kardex.view');
            Route::get('/kardex/export', [KardexController::class, 'export'])->middleware('permission:kardex.view');

            // Notas de almacén (Spec 016)
            Route::get('/warehouse-notes', [WarehouseNoteController::class, 'index'])->middleware('permission:warehouse_notes.view');
            Route::post('/warehouse-notes', [WarehouseNoteController::class, 'store'])->middleware('permission:warehouse_notes.create');
            Route::get('/warehouse-notes/{id}', [WarehouseNoteController::class, 'show'])->whereUuid('id')->middleware('permission:warehouse_notes.view');
            Route::put('/warehouse-notes/{id}', [WarehouseNoteController::class, 'update'])->whereUuid('id')->middleware('permission:warehouse_notes.update');
            Route::post('/warehouse-notes/{id}/approve', [WarehouseNoteController::class, 'approve'])->whereUuid('id')->middleware('permission:warehouse_notes.approve');
            Route::post('/warehouse-notes/{id}/cancel', [WarehouseNoteController::class, 'cancel'])->whereUuid('id')->middleware('permission:warehouse_notes.cancel');

            // Toma de inventario (Spec 016)
            Route::get('/counts', [InventoryCountController::class, 'index'])->middleware('permission:inventory_counts.view');
            Route::post('/counts', [InventoryCountController::class, 'store'])->middleware('permission:inventory_counts.manage');
            Route::get('/counts/{id}', [InventoryCountController::class, 'show'])->whereUuid('id')->middleware('permission:inventory_counts.view');
            Route::put('/counts/{id}/items', [InventoryCountController::class, 'updateItems'])->whereUuid('id')->middleware('permission:inventory_counts.manage');
            Route::post('/counts/{id}/close', [InventoryCountController::class, 'close'])->whereUuid('id')->middleware('permission:inventory_counts.close');
            Route::post('/counts/{id}/cancel', [InventoryCountController::class, 'cancel'])->whereUuid('id')->middleware('permission:inventory_counts.close');
        });
    });

    // Módulo Finance — Maestros (Spec 017)
    Route::prefix('finance')->group(function () {
        // Tipos de cambio
        Route::get('/exchange-rates/current', [ExchangeRateController::class, 'current']);
        Route::put('/exchange-rates', [ExchangeRateController::class, 'upsert'])->middleware('permission:exchange_rates.manage');
        Route::get('/exchange-rates', [ExchangeRateController::class, 'index'])->middleware('permission:finance_settings.view');

        // Monedas
        Route::get('/currencies/options', [CurrencyController::class, 'options']);
        Route::get('/currencies', [CurrencyController::class, 'index'])->middleware('permission:finance_settings.view');
        Route::post('/currencies', [CurrencyController::class, 'store'])->middleware('permission:finance_settings.manage');
        Route::get('/currencies/{id}', [CurrencyController::class, 'show'])->whereUuid('id')->middleware('permission:finance_settings.view');
        Route::match(['put', 'patch'], '/currencies/{id}', [CurrencyController::class, 'update'])->whereUuid('id')->middleware('permission:finance_settings.manage');
        Route::delete('/currencies/{id}', [CurrencyController::class, 'destroy'])->whereUuid('id')->middleware('permission:finance_settings.manage');

        // Bancos
        Route::get('/banks/options', [BankController::class, 'options']);
        Route::get('/banks', [BankController::class, 'index'])->middleware('permission:finance_settings.view');
        Route::post('/banks', [BankController::class, 'store'])->middleware('permission:finance_settings.manage');
        Route::get('/banks/{id}', [BankController::class, 'show'])->whereUuid('id')->middleware('permission:finance_settings.view');
        Route::match(['put', 'patch'], '/banks/{id}', [BankController::class, 'update'])->whereUuid('id')->middleware('permission:finance_settings.manage');
        Route::delete('/banks/{id}', [BankController::class, 'destroy'])->whereUuid('id')->middleware('permission:finance_settings.manage');

        // Cuentas bancarias
        Route::get('/bank-accounts/options', [BankAccountController::class, 'options']);
        Route::get('/bank-accounts', [BankAccountController::class, 'index'])->middleware('permission:finance_settings.view|bank_movements.view');
        Route::post('/bank-accounts', [BankAccountController::class, 'store'])->middleware('permission:finance_settings.manage');
        Route::get('/bank-accounts/{id}', [BankAccountController::class, 'show'])->whereUuid('id')->middleware('permission:finance_settings.view|bank_movements.view');
        Route::match(['put', 'patch'], '/bank-accounts/{id}', [BankAccountController::class, 'update'])->whereUuid('id')->middleware('permission:finance_settings.manage');
        Route::delete('/bank-accounts/{id}', [BankAccountController::class, 'destroy'])->whereUuid('id')->middleware('permission:finance_settings.manage');

        // Métodos de pago
        Route::get('/payment-methods/options', [PaymentMethodController::class, 'options']);
        Route::get('/payment-methods', [PaymentMethodController::class, 'index'])->middleware('permission:finance_settings.view');
        Route::post('/payment-methods', [PaymentMethodController::class, 'store'])->middleware('permission:finance_settings.manage');
        Route::get('/payment-methods/{id}', [PaymentMethodController::class, 'show'])->whereUuid('id')->middleware('permission:finance_settings.view');
        Route::match(['put', 'patch'], '/payment-methods/{id}', [PaymentMethodController::class, 'update'])->whereUuid('id')->middleware('permission:finance_settings.manage');
        Route::delete('/payment-methods/{id}', [PaymentMethodController::class, 'destroy'])->whereUuid('id')->middleware('permission:finance_settings.manage');

        // Diagnóstico de la configuración contable (Spec 032)
        Route::get('/settings/health', FinanceSettingsHealthController::class)->middleware('permission:finance_settings.view');

        // Plan de cuentas
        Route::get('/financial-accounts/tree', [FinancialAccountController::class, 'tree']);
        Route::get('/financial-accounts/options', [FinancialAccountController::class, 'options']);
        Route::get('/financial-accounts', [FinancialAccountController::class, 'index'])->middleware('permission:finance_settings.view');
        Route::post('/financial-accounts', [FinancialAccountController::class, 'store'])->middleware('permission:finance_settings.manage');
        Route::get('/financial-accounts/{id}', [FinancialAccountController::class, 'show'])->whereUuid('id')->middleware('permission:finance_settings.view');
        Route::match(['put', 'patch'], '/financial-accounts/{id}', [FinancialAccountController::class, 'update'])->whereUuid('id')->middleware('permission:finance_settings.manage');
        Route::delete('/financial-accounts/{id}', [FinancialAccountController::class, 'destroy'])->whereUuid('id')->middleware('permission:finance_settings.manage');

        // Conceptos de ingreso y egreso
        Route::get('/concepts/options', [FinanceConceptController::class, 'options']);
        Route::get('/concepts', [FinanceConceptController::class, 'index'])->middleware('permission:finance_settings.view');
        Route::post('/concepts', [FinanceConceptController::class, 'store'])->middleware('permission:finance_settings.manage');
        Route::get('/concepts/{id}', [FinanceConceptController::class, 'show'])->whereUuid('id')->middleware('permission:finance_settings.view');
        Route::match(['put', 'patch'], '/concepts/{id}', [FinanceConceptController::class, 'update'])->whereUuid('id')->middleware('permission:finance_settings.manage');
        Route::delete('/concepts/{id}', [FinanceConceptController::class, 'destroy'])->whereUuid('id')->middleware('permission:finance_settings.manage');

        // Cajas y sesiones (Spec 018)
        Route::middleware('branch')->group(function () {
            Route::get('/boxes/available', [BoxController::class, 'available'])->middleware('permission:cash_sessions.operate');
            Route::get('/boxes/{id}/suggested-opening', [BoxController::class, 'suggestedOpening'])->whereUuid('id')->middleware('permission:cash_sessions.operate');
            Route::get('/boxes', [BoxController::class, 'index'])->middleware('permission:boxes.view');
            Route::post('/boxes', [BoxController::class, 'store'])->middleware('permission:boxes.manage');
            Route::get('/boxes/{id}', [BoxController::class, 'show'])->whereUuid('id')->middleware('permission:boxes.view');
            Route::match(['put', 'patch'], '/boxes/{id}', [BoxController::class, 'update'])->whereUuid('id')->middleware('permission:boxes.manage');
            Route::delete('/boxes/{id}', [BoxController::class, 'destroy'])->whereUuid('id')->middleware('permission:boxes.manage');
            Route::put('/boxes/{id}/roles', [BoxController::class, 'syncRoles'])->whereUuid('id')->middleware('permission:boxes.manage');

            Route::get('/cash-sessions', [CashSessionController::class, 'index'])->middleware('permission:cash_sessions.view|cash_sessions.operate');
            Route::post('/cash-sessions', [CashSessionController::class, 'open'])->middleware('permission:cash_sessions.operate');
            Route::get('/cash-sessions/{id}', [CashSessionController::class, 'show'])->whereUuid('id')->middleware('permission:cash_sessions.view|cash_sessions.operate');
            Route::get('/cash-sessions/{id}/movements', [CashMovementController::class, 'index'])->whereUuid('id')->middleware('permission:cash_sessions.view|cash_sessions.operate');
            Route::post('/cash-sessions/{id}/movements', [CashMovementController::class, 'store'])->whereUuid('id')->middleware('permission:cash_sessions.operate');
            Route::post('/cash-sessions/{id}/close', [CashSessionController::class, 'close'])->whereUuid('id')->middleware('permission:cash_sessions.operate');
            Route::post('/cash-sessions/{id}/review', [CashSessionController::class, 'review'])->whereUuid('id')->middleware('permission:cash_sessions.review');
            Route::post('/cash-movements/{id}/cancel', [CashMovementController::class, 'cancel'])->whereUuid('id')->middleware('permission:cash_movements.cancel');

            // Transferencias entre cajas (Spec 019)
            Route::get('/cash-transfers', [CashTransferController::class, 'index'])->middleware('permission:cash_transfers.view|cash_transfers.create');
            Route::get('/cash-transfers/pending-count', [CashTransferController::class, 'pendingCount'])->middleware('permission:cash_transfers.approve');
            Route::post('/cash-transfers', [CashTransferController::class, 'store'])->middleware('permission:cash_transfers.create');
            Route::get('/cash-transfers/{id}', [CashTransferController::class, 'show'])->whereUuid('id')->middleware('permission:cash_transfers.view|cash_transfers.create');
            Route::post('/cash-transfers/{id}/approve', [CashTransferController::class, 'approve'])->whereUuid('id')->middleware('permission:cash_transfers.approve');
            Route::post('/cash-transfers/{id}/reject', [CashTransferController::class, 'reject'])->whereUuid('id')->middleware('permission:cash_transfers.approve');
            Route::post('/cash-transfers/{id}/cancel', [CashTransferController::class, 'cancel'])->whereUuid('id')->middleware('permission:cash_transfers.create');

            // Bancos (Spec 020)
            Route::get('/bank-accounts/summary', [BankAccountSummaryController::class, 'index'])->middleware('permission:bank_movements.view');
            Route::get('/bank-accounts/{id}/movements', [BankMovementController::class, 'index'])->whereUuid('id')->middleware('permission:bank_movements.view');
            Route::post('/bank-accounts/{id}/movements', [BankMovementController::class, 'store'])->whereUuid('id')->middleware('permission:bank_movements.create');
            Route::get('/bank-accounts/{id}/periods', [BankPeriodController::class, 'index'])->whereUuid('id')->middleware('permission:bank_movements.view');
            Route::post('/bank-accounts/{id}/periods', [BankPeriodController::class, 'openInitial'])->whereUuid('id')->middleware('permission:bank_periods.close');
            Route::post('/bank-periods/{id}/close', [BankPeriodController::class, 'close'])->whereUuid('id')->middleware('permission:bank_periods.close');
            Route::get('/bank-movements/{id}/voucher', [BankMovementController::class, 'voucher'])->whereUuid('id')->middleware('permission:bank_movements.view');
            Route::post('/bank-movements/{id}/cancel', [BankMovementController::class, 'cancel'])->whereUuid('id')->middleware('permission:bank_operations.cancel');
            Route::get('/bank-operations', [BankOperationController::class, 'index'])->middleware('permission:bank_movements.view|bank_operations.create');
            Route::post('/bank-operations', [BankOperationController::class, 'store'])->middleware('permission:bank_operations.create');
            Route::get('/bank-operations/{id}', [BankOperationController::class, 'show'])->whereUuid('id')->middleware('permission:bank_movements.view|bank_operations.create');
            Route::post('/bank-operations/{id}/cancel', [BankOperationController::class, 'cancel'])->whereUuid('id')->middleware('permission:bank_operations.cancel');
            Route::get('/bank-operations/{id}/voucher', [BankOperationController::class, 'voucher'])->whereUuid('id')->middleware('permission:bank_movements.view|bank_operations.create');
        });
    });

    // Módulo Purchases — Proveedores (Spec 021)
    Route::prefix('purchases')->group(function () {
        Route::get('/providers/options', [ProviderController::class, 'options'])->middleware('permission:providers.view');
        Route::get('/providers', [ProviderController::class, 'index'])->middleware('permission:providers.view');
        Route::post('/providers', [ProviderController::class, 'store'])->middleware('permission:providers.create');
        Route::get('/providers/{id}', [ProviderController::class, 'show'])->whereUuid('id')->middleware('permission:providers.view');
        Route::match(['put', 'patch'], '/providers/{id}', [ProviderController::class, 'update'])->whereUuid('id')->middleware('permission:providers.update');
        Route::delete('/providers/{id}', [ProviderController::class, 'destroy'])->whereUuid('id')->middleware('permission:providers.delete');
        Route::put('/providers/{id}/contacts', [ProviderContactController::class, 'sync'])->whereUuid('id')->middleware('permission:providers.update');
        Route::put('/providers/{id}/bank-accounts', [ProviderBankAccountController::class, 'sync'])->whereUuid('id')->middleware('permission:providers.update');
        Route::get('/providers/{id}/prices', [ProviderPriceController::class, 'index'])->whereUuid('id')->middleware('permission:providers.view');
        Route::post('/providers/{id}/prices', [ProviderPriceController::class, 'store'])->whereUuid('id')->middleware('permission:providers.update');
        Route::get('/provider-prices/compare', [ProviderPriceController::class, 'compare'])->middleware('permission:providers.view');
        Route::put('/provider-prices/{id}', [ProviderPriceController::class, 'update'])->whereUuid('id')->middleware('permission:providers.update');
        Route::delete('/provider-prices/{id}', [ProviderPriceController::class, 'destroy'])->whereUuid('id')->middleware('permission:providers.update');

        // Requerimientos de compra (Spec 022)
        Route::middleware('branch')->group(function () {
            Route::get('/requests/pending-items', [PurchaseRequestController::class, 'pendingItems'])->middleware('permission:purchase_orders.create');
            Route::post('/requests/from-low-stock', [PurchaseRequestController::class, 'fromLowStock'])->middleware('permission:purchase_requests.create');
            Route::get('/requests', [PurchaseRequestController::class, 'index'])->middleware('permission:purchase_requests.view');
            Route::post('/requests', [PurchaseRequestController::class, 'store'])->middleware('permission:purchase_requests.create');
            Route::get('/requests/{id}', [PurchaseRequestController::class, 'show'])->whereUuid('id')->middleware('permission:purchase_requests.view');
            Route::put('/requests/{id}', [PurchaseRequestController::class, 'update'])->whereUuid('id')->middleware('permission:purchase_requests.create');
            Route::post('/requests/{id}/submit', [PurchaseRequestController::class, 'submit'])->whereUuid('id')->middleware('permission:purchase_requests.create');
            Route::post('/requests/{id}/approve', [PurchaseRequestController::class, 'approve'])->whereUuid('id')->middleware('permission:purchase_requests.approve');
            Route::post('/requests/{id}/reject', [PurchaseRequestController::class, 'reject'])->whereUuid('id')->middleware('permission:purchase_requests.approve');
            Route::post('/requests/{id}/cancel', [PurchaseRequestController::class, 'cancel'])->whereUuid('id')->middleware('permission:purchase_requests.approve');

            // Órdenes de compra (Spec 022)
            Route::get('/orders', [PurchaseOrderController::class, 'index'])->middleware('permission:purchase_orders.view');
            Route::post('/orders', [PurchaseOrderController::class, 'store'])->middleware('permission:purchase_orders.create');
            Route::get('/orders/{id}', [PurchaseOrderController::class, 'show'])->whereUuid('id')->middleware('permission:purchase_orders.view');
            Route::put('/orders/{id}', [PurchaseOrderController::class, 'update'])->whereUuid('id')->middleware('permission:purchase_orders.update');
            Route::post('/orders/{id}/submit', [PurchaseOrderController::class, 'submit'])->whereUuid('id')->middleware('permission:purchase_orders.create');
            Route::post('/orders/{id}/approve', [PurchaseOrderController::class, 'approve'])->whereUuid('id')->middleware('permission:purchase_orders.approve');
            Route::post('/orders/{id}/reject', [PurchaseOrderController::class, 'reject'])->whereUuid('id')->middleware('permission:purchase_orders.approve');
            Route::post('/orders/{id}/close', [PurchaseOrderController::class, 'close'])->whereUuid('id')->middleware('permission:purchase_orders.cancel');
            Route::post('/orders/{id}/cancel', [PurchaseOrderController::class, 'cancel'])->whereUuid('id')->middleware('permission:purchase_orders.cancel');
            Route::get('/orders/{id}/pdf', [PurchaseOrderController::class, 'pdf'])->whereUuid('id')->middleware('permission:purchase_orders.view');

            // Recepciones de compra (Spec 023)
            Route::get('/orders/{id}/pending-items', [PurchaseReceiptController::class, 'pendingItems'])->whereUuid('id')->middleware('permission:purchase_receipts.create');
            Route::post('/orders/{id}/receive-all', [PurchaseReceiptController::class, 'receiveAll'])->whereUuid('id')->middleware(['permission:purchase_receipts.create', 'permission:purchase_receipts.confirm']);
            Route::get('/receipts', [PurchaseReceiptController::class, 'index'])->middleware('permission:purchase_receipts.view');
            Route::post('/receipts', [PurchaseReceiptController::class, 'store'])->middleware('permission:purchase_receipts.create');
            Route::get('/receipts/{id}', [PurchaseReceiptController::class, 'show'])->whereUuid('id')->middleware('permission:purchase_receipts.view');
            Route::put('/receipts/{id}', [PurchaseReceiptController::class, 'update'])->whereUuid('id')->middleware('permission:purchase_receipts.create');
            Route::post('/receipts/{id}/confirm', [PurchaseReceiptController::class, 'confirm'])->whereUuid('id')->middleware('permission:purchase_receipts.confirm');
            Route::post('/receipts/{id}/cancel', [PurchaseReceiptController::class, 'cancel'])->whereUuid('id')->middleware('permission:purchase_receipts.cancel');

            // Facturas de proveedor, pagos y cuentas por pagar (Spec 024)
            Route::get('/supplier-invoices', [SupplierInvoiceController::class, 'index'])->middleware('permission:supplier_invoices.view');
            Route::get('/supplier-invoices/prefill', [SupplierInvoiceController::class, 'prefill'])->middleware('permission:supplier_invoices.create');
            Route::post('/supplier-invoices', [SupplierInvoiceController::class, 'store'])->middleware('permission:supplier_invoices.create');
            Route::get('/supplier-invoices/{id}', [SupplierInvoiceController::class, 'show'])->whereUuid('id')->middleware('permission:supplier_invoices.view');
            Route::get('/supplier-invoices/{id}/attachment', [SupplierInvoiceController::class, 'attachment'])->whereUuid('id')->middleware('permission:supplier_invoices.view');
            Route::post('/supplier-invoices/{id}/cancel', [SupplierInvoiceController::class, 'cancel'])->whereUuid('id')->middleware('permission:supplier_invoices.cancel');
            Route::get('/supplier-payments', [SupplierPaymentController::class, 'index'])->middleware('permission:supplier_payments.view');
            Route::get('/supplier-payments/open-installments', [SupplierPaymentController::class, 'openInstallments'])->middleware('permission:supplier_payments.create');
            Route::post('/supplier-payments', [SupplierPaymentController::class, 'store'])->middleware('permission:supplier_payments.create');
            Route::get('/supplier-payments/{id}', [SupplierPaymentController::class, 'show'])->whereUuid('id')->middleware('permission:supplier_payments.view');
            Route::post('/supplier-payments/{id}/cancel', [SupplierPaymentController::class, 'cancel'])->whereUuid('id')->middleware('permission:supplier_payments.cancel');
            Route::get('/accounts-payable', [AccountsPayableController::class, 'index'])->middleware('permission:supplier_invoices.view');
            Route::get('/accounts-payable/aging', [AccountsPayableController::class, 'aging'])->middleware('permission:supplier_invoices.view');
            Route::get('/providers/{id}/statement', [AccountsPayableController::class, 'statement'])->whereUuid('id')->middleware('permission:supplier_invoices.view');
        });
    });

    // Módulo Sales — Cotizaciones (Spec 026)
    Route::prefix('sales')->middleware('branch')->group(function () {
        Route::get('/quotations', [QuotationController::class, 'index'])->middleware('permission:quotations.view');
        Route::post('/quotations', [QuotationController::class, 'store'])->middleware('permission:quotations.create');
        Route::get('/quotations/{id}', [QuotationController::class, 'show'])->whereUuid('id')->middleware('permission:quotations.view');
        Route::put('/quotations/{id}', [QuotationController::class, 'update'])->whereUuid('id')->middleware('permission:quotations.update');
        Route::get('/quotations/{id}/availability', [QuotationController::class, 'availability'])->whereUuid('id')->middleware('permission:quotations.view');
        Route::get('/quotations/{id}/pdf', [QuotationController::class, 'pdf'])->whereUuid('id')->middleware('permission:quotations.view');
        Route::post('/quotations/{id}/duplicate', [QuotationController::class, 'duplicate'])->whereUuid('id')->middleware('permission:quotations.create');
        Route::post('/quotations/{id}/send', [QuotationController::class, 'send'])->whereUuid('id')->middleware('permission:quotations.update');
        Route::post('/quotations/{id}/accept', [QuotationController::class, 'accept'])->whereUuid('id')->middleware('permission:quotations.update');
        Route::post('/quotations/{id}/reject', [QuotationController::class, 'reject'])->whereUuid('id')->middleware('permission:quotations.update');
        Route::post('/quotations/{id}/cancel', [QuotationController::class, 'cancel'])->whereUuid('id')->middleware('permission:quotations.cancel');

        // Series y correlativos de comprobantes (Spec 027)
        Route::get('/document-series/available', [DocumentSeriesController::class, 'available'])->middleware('permission:sales.create');
        Route::get('/document-series', [DocumentSeriesController::class, 'index'])->middleware('permission:document_series.view');
        Route::post('/document-series', [DocumentSeriesController::class, 'store'])->middleware('permission:document_series.manage');
        Route::get('/document-series/{id}', [DocumentSeriesController::class, 'show'])->whereUuid('id')->middleware('permission:document_series.view');
        Route::match(['put', 'patch'], '/document-series/{id}', [DocumentSeriesController::class, 'update'])->whereUuid('id')->middleware('permission:document_series.manage');
        Route::delete('/document-series/{id}', [DocumentSeriesController::class, 'destroy'])->whereUuid('id')->middleware('permission:document_series.manage');

        // Emisión y gestión de comprobantes de venta (Spec 027)
        Route::get('/sales/prefill', [SaleController::class, 'prefill'])->middleware('permission:sales.create');
        Route::get('/sales', [SaleController::class, 'index'])->middleware('permission:sales.view');
        Route::post('/sales', [SaleController::class, 'store'])->middleware('permission:sales.create');
        Route::get('/sales/{id}', [SaleController::class, 'show'])->whereUuid('id')->middleware('permission:sales.view');
        Route::get('/sales/{id}/pdf', [SaleController::class, 'pdf'])->whereUuid('id')->middleware('permission:sales.view');
        Route::post('/sales/{id}/cancel', [SaleController::class, 'cancel'])->whereUuid('id')->middleware('permission:sales.cancel');
        Route::post('/sales/{id}/einvoice/retry', [SaleController::class, 'retryEInvoice'])->whereUuid('id')->middleware('permission:document_series.manage');

        // Cuentas por Cobrar y Cobranzas (Spec 028)
        Route::get('/receivables/export', [ReceivableController::class, 'export'])->middleware('permission:receivables.export');
        Route::get('/receivables/aging', [ReceivableController::class, 'aging'])->middleware('permission:receivables.view');
        Route::get('/receivables', [ReceivableController::class, 'index'])->middleware('permission:receivables.view');
        Route::post('/sales/{id}/reschedule', [ReceivableController::class, 'reschedule'])->whereUuid('id')->middleware('permission:receivables.reschedule');

        Route::get('/collections/export', [CollectionController::class, 'export'])->middleware('permission:receivables.export');
        Route::get('/collections', [CollectionController::class, 'index'])->middleware('permission:receivables.view');
        Route::post('/collections', [CollectionController::class, 'store'])->middleware('permission:collections.create');
        Route::get('/collections/{id}', [CollectionController::class, 'show'])->whereUuid('id')->middleware('permission:receivables.view');
        Route::get('/collections/{id}/pdf', [CollectionController::class, 'pdf'])->whereUuid('id')->middleware('permission:receivables.view');
        Route::post('/collections/{id}/cancel', [CollectionController::class, 'cancel'])->whereUuid('id')->middleware('permission:collections.cancel');

        // Notas de Crédito (Spec 029)
        Route::get('/credit-notes/prefill', [CreditNoteController::class, 'prefill'])->middleware('permission:credit_notes.create');
        Route::get('/credit-notes/summary', [CreditNoteController::class, 'summary'])->middleware('permission:credit_notes.view');
        Route::get('/credit-notes', [CreditNoteController::class, 'index'])->middleware('permission:credit_notes.view');
        Route::post('/credit-notes', [CreditNoteController::class, 'store'])->middleware('permission:credit_notes.create');
        Route::get('/credit-notes/{id}', [CreditNoteController::class, 'show'])->whereUuid('id')->middleware('permission:credit_notes.view');
        Route::get('/credit-notes/{id}/pdf', [CreditNoteController::class, 'pdf'])->whereUuid('id')->middleware('permission:credit_notes.view');
        Route::post('/credit-notes/{id}/cancel', [CreditNoteController::class, 'cancel'])->whereUuid('id')->middleware('permission:credit_notes.cancel');
        Route::post('/credit-notes/{id}/einvoice/retry', [CreditNoteController::class, 'retryEInvoice'])->whereUuid('id')->middleware('permission:document_series.manage');
    });
});
