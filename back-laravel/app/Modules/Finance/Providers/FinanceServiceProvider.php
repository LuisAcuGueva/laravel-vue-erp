<?php

declare(strict_types=1);

namespace App\Modules\Finance\Providers;

use App\Modules\Finance\Console\CheckAccountingSetupCommand;
use App\Modules\Finance\Console\VerifyBankBalancesCommand;
use App\Modules\Finance\Models\Bank;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\BankMovement;
use App\Modules\Finance\Models\BankOperation;
use App\Modules\Finance\Models\BankPeriod;
use App\Modules\Finance\Models\Box;
use App\Modules\Finance\Models\CashMovement;
use App\Modules\Finance\Models\CashSession;
use App\Modules\Finance\Models\CashTransfer;
use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Models\ExchangeRate;
use App\Modules\Finance\Models\FinanceConcept;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Finance\Services\AccountingSetupHealthService;
use App\Modules\Finance\Services\BankAccountService;
use App\Modules\Finance\Services\BankLedgerService;
use App\Modules\Finance\Services\BankOperationService;
use App\Modules\Finance\Services\BankPeriodService;
use App\Modules\Finance\Services\BankQueryService;
use App\Modules\Finance\Services\BankService;
use App\Modules\Finance\Services\CashTransferService;
use App\Modules\Finance\Services\CurrencyService;
use App\Modules\Finance\Services\ExchangeRateService;
use App\Modules\Finance\Services\FinanceConceptService;
use App\Modules\Finance\Services\FinancialAccountService;
use App\Modules\Finance\Services\MoneySettlementService;
use App\Modules\Finance\Services\PaymentMethodService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class FinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurrencyService::class);
        $this->app->singleton(ExchangeRateService::class);
        $this->app->singleton(BankService::class);
        $this->app->singleton(BankAccountService::class);
        $this->app->singleton(PaymentMethodService::class);
        $this->app->singleton(FinancialAccountService::class);
        $this->app->singleton(FinanceConceptService::class);
        $this->app->singleton(CashTransferService::class);
        $this->app->singleton(BankLedgerService::class);
        $this->app->singleton(BankPeriodService::class);
        $this->app->singleton(BankOperationService::class);
        $this->app->singleton(BankQueryService::class);
        $this->app->singleton(MoneySettlementService::class);
        $this->app->singleton(AccountingSetupHealthService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                VerifyBankBalancesCommand::class,
                CheckAccountingSetupCommand::class,
            ]);
        }

        Relation::morphMap([
            'cash_session' => CashSession::class,
            'cash_movement' => CashMovement::class,
            'cash_transfer' => CashTransfer::class,
            'bank_operation' => BankOperation::class,
            'bank_movement' => BankMovement::class,
            'bank_account' => BankAccount::class,
            'exchange_rate' => ExchangeRate::class,
            'bank' => Bank::class,
            'bank_period' => BankPeriod::class,
            'box' => Box::class,
            'currency' => Currency::class,
            'finance_concept' => FinanceConcept::class,
            'financial_account' => FinancialAccount::class,
            'payment_method' => PaymentMethod::class,
        ], merge: true);
    }
}
