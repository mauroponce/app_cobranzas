<?php

namespace App\Providers;

use App\Domain\Tenancy\RoleMatrix;
use App\Domain\Tenancy\TenantAuthorization;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use App\Policies\CustomerPolicy;
use App\Policies\ProductPolicy;
use App\Policies\QuotationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Quotation::class, QuotationPolicy::class);

        foreach (RoleMatrix::CAPABILITIES as $capability) {
            Gate::define($capability, fn (User $user) => TenantAuthorization::allows(request(), $user, $capability));
        }

        Gate::define('platform.access', fn (User $user) => $user->status === 'active'
            && $user->platformAdministrator()->exists());
    }
}
