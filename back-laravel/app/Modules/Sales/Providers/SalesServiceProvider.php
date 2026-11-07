<?php

declare(strict_types=1);

namespace App\Modules\Sales\Providers;

use App\Modules\Sales\Console\ExpireQuotationsCommand;
use App\Modules\Sales\EInvoicing\EInvoicingManager;
use App\Modules\Sales\Events\SaleCancelled;
use App\Modules\Sales\Hooks\CreateReceivableInstallments;
use App\Modules\Sales\Listeners\CancelReceivableInstallments;
use App\Modules\Sales\Models\Collection;
use App\Modules\Sales\Models\CreditNote;
use App\Modules\Sales\Models\DocumentSeries;
use App\Modules\Sales\Models\Quotation;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SalePayment;
use App\Modules\Sales\Services\CollectionService;
use App\Modules\Sales\Services\CreditNoteCancellationService;
use App\Modules\Sales\Services\CreditNoteIssuanceService;
use App\Modules\Sales\Services\CreditNoteSettlementService;
use App\Modules\Sales\Services\CreditPolicyService;
use App\Modules\Sales\Services\DiscountPolicyService;
use App\Modules\Sales\Services\DocumentSeriesService;
use App\Modules\Sales\Services\InstallmentScheduleService;
use App\Modules\Sales\Services\ReceivableQueryService;
use App\Modules\Sales\Services\SaleCancellationService;
use App\Modules\Sales\Services\SaleIssuanceService;
use App\Modules\Sales\Services\SalePricingService;
use App\Modules\Sales\Services\SaleQueryService;
use App\Modules\Sales\Services\SaleService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class SalesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SalePricingService::class);
        $this->app->singleton(DiscountPolicyService::class);
        $this->app->singleton(DocumentSeriesService::class);
        $this->app->singleton(CreditPolicyService::class);
        $this->app->singleton(InstallmentScheduleService::class);
        $this->app->singleton(CreateReceivableInstallments::class);
        $this->app->singleton(CollectionService::class);
        $this->app->singleton(ReceivableQueryService::class);

        $this->app->singleton(CreditNoteSettlementService::class);
        $this->app->singleton(CreditNoteIssuanceService::class);
        $this->app->singleton(CreditNoteCancellationService::class);

        $this->app->tag([
            CreateReceivableInstallments::class,
        ], 'sales.after_issue');

        $this->app->when(SaleIssuanceService::class)
            ->needs('$afterIssueHooks')
            ->giveTagged('sales.after_issue');

        $this->app->singleton(SaleIssuanceService::class);
        $this->app->singleton(SaleCancellationService::class);
        $this->app->singleton(SaleQueryService::class);
        $this->app->singleton(SaleService::class);
        $this->app->singleton(EInvoicingManager::class, fn ($app) => new EInvoicingManager($app));
    }

    public function boot(): void
    {
        Event::listen(SaleCancelled::class, CancelReceivableInstallments::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ExpireQuotationsCommand::class,
            ]);
        }

        Relation::morphMap([
            'quotation' => Quotation::class,
            'sale' => Sale::class,
            'sale_payment' => SalePayment::class,
            'collection' => Collection::class,
            'credit_note' => CreditNote::class,
            'document_series' => DocumentSeries::class,
        ], merge: true);
    }
}
