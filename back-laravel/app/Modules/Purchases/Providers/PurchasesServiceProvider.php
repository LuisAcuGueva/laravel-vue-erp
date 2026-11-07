<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Providers;

use App\Modules\Purchases\Models\Provider;
use App\Modules\Purchases\Models\ProviderBankAccount;
use App\Modules\Purchases\Models\ProviderPrice;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseReceipt;
use App\Modules\Purchases\Models\PurchaseRequest;
use App\Modules\Purchases\Models\SupplierInvoice;
use App\Modules\Purchases\Models\SupplierPayment;
use App\Modules\Purchases\Services\ProviderBankAccountService;
use App\Modules\Purchases\Services\ProviderContactService;
use App\Modules\Purchases\Services\ProviderPriceService;
use App\Modules\Purchases\Services\ProviderService;
use App\Modules\Purchases\Services\PurchaseReceiptConfirmationService;
use App\Modules\Purchases\Services\PurchaseReceiptService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class PurchasesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProviderService::class);
        $this->app->singleton(ProviderContactService::class);
        $this->app->singleton(ProviderBankAccountService::class);
        $this->app->singleton(ProviderPriceService::class);
        $this->app->singleton(PurchaseReceiptService::class);
        $this->app->singleton(PurchaseReceiptConfirmationService::class);
    }

    public function boot(): void
    {
        Relation::morphMap([
            'purchase_order' => PurchaseOrder::class,
            'purchase_receipt' => PurchaseReceipt::class,
            'supplier_invoice' => SupplierInvoice::class,
            'supplier_payment' => SupplierPayment::class,
            'purchase_request' => PurchaseRequest::class,
            'provider_price' => ProviderPrice::class,
            'provider' => Provider::class,
            'provider_bank_account' => ProviderBankAccount::class,
        ], merge: true);
    }
}
