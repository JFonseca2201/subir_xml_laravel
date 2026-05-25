<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Implicitly grant "Super Admin" role all permissions
        // This works in the app by using gate-related functions like auth()->user->can() and @can()
        Gate::before(function ($user, $ability) {
            return $user->hasRole('Super-Admin') ? true : null;
        });

        // Ensure database morph types from before the refactor can still resolve
        Relation::morphMap([
            'App\Models\AporteCapital' => \App\Models\Partner\AporteCapital::class,
            'App\Models\PartnerContribution' => \App\Models\Partner\PartnerContribution::class,
            'App\Models\EmployeeAdvance' => \App\Models\Employee\EmployeeAdvance::class,
            'App\Models\EmployeePayment' => \App\Models\Employee\EmployeePayment::class,
            'App\Models\InternalTransfer' => \App\Models\Finance\InternalTransfer::class,
            'App\Models\Transfer' => \App\Models\Finance\Transfer::class,
            'App\Models\Transaction' => \App\Models\Finance\Transaction::class,
            'App\Models\MovimientoCuenta' => \App\Models\Finance\MovimientoCuenta::class,
            'App\Models\PaymentDistribution' => \App\Models\Finance\PaymentDistribution::class,
            'App\Models\FinanceRecord' => \App\Models\Finance\FinanceRecord::class,
            'App\Models\FinancialMovement' => \App\Models\Finance\FinancialMovement::class,
            'App\Models\Invoice' => \App\Models\Invoice\Invoice::class,
            'App\Models\WorkOrder' => \App\Models\WorkOrder\WorkOrder::class,
            'App\Models\Supplier' => \App\Models\Supplier\Supplier::class,
            'App\Models\Partner' => \App\Models\Partner\Partner::class,
            'App\Models\Account' => \App\Models\Finance\Account::class,
            'App\Models\Employee' => \App\Models\Employee\Employee::class,
        ]);
    }
}
