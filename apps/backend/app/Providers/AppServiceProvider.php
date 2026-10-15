<?php

namespace App\Providers;

use App\Domain\Tenancy\RoleMatrix;
use App\Domain\Tenancy\TenantAuthorization;
use App\Http\Middleware\ResolveAdminTenant;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Sale;
use App\Models\SaleApplication;
use App\Models\User;
use App\Policies\CustomerDocumentPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\ProductPolicy;
use App\Policies\QuotationPolicy;
use App\Policies\SaleApplicationPolicy;
use App\Policies\SalePolicy;
use App\Support\ProtectedDatabaseCommand;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            $driver = (string) config('database.default');
            $connection = config("database.connections.{$driver}", []);

            ProtectedDatabaseCommand::ensureAllowed(
                $event->command,
                (string) app()->environment(),
                $driver,
                is_array($connection) ? $connection : [],
                (bool) config('database.allow_destructive_commands', false),
            );
        });

        Livewire::addPersistentMiddleware([ResolveAdminTenant::class]);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Quotation::class, QuotationPolicy::class);
        Gate::policy(Sale::class, SalePolicy::class);
        Gate::policy(SaleApplication::class, SaleApplicationPolicy::class);
        Gate::policy(CustomerDocument::class, CustomerDocumentPolicy::class);

        foreach (RoleMatrix::CAPABILITIES as $capability) {
            Gate::define($capability, fn (User $user) => TenantAuthorization::allows(request(), $user, $capability));
        }

        Gate::define('platform.access', fn (User $user) => $user->status === 'active'
            && $user->platformAdministrator()->exists());
    }
}
