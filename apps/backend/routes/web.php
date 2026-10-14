<?php

use App\Http\Controllers\AcceptInvitationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PlatformTenantController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\TenantInvitationController;
use App\Http\Controllers\TenantSelectionController;
use App\Http\Controllers\TenantUserController;
use App\Models\CollectionRoute;
use App\Models\CustomerCollectorAssignment;
use App\Models\TenantMembership;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::get('/invitations/accept/{token}', [AcceptInvitationController::class, 'show'])->name('invitations.accept.show');
Route::post('/invitations/accept/{token}', [AcceptInvitationController::class, 'accept'])->middleware('throttle:10,1')->name('invitations.accept');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.store');
    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/tenants/choose', [TenantSelectionController::class, 'show'])->name('tenants.choose');
    Route::post('/tenants/choose', [TenantSelectionController::class, 'select'])->name('tenants.select');
    Route::get('/platform', [PlatformTenantController::class, 'index'])->name('platform.tenants');
    Route::post('/platform/tenants', [PlatformTenantController::class, 'store'])->name('platform.tenants.store');
    Route::patch('/platform/tenants/{tenant}', [PlatformTenantController::class, 'update'])->name('platform.tenants.update');

    Route::middleware('tenant.admin')->group(function (): void {
        Route::view('/dashboard', 'dashboard')->name('dashboard');
        Route::view('/admin/collections', 'collections.index')->name('collections.index');
        Route::view('/admin/collections/portfolios', 'collections.portfolios')->name('collections.portfolios');
        Route::view('/admin/collections/structures', 'collections.structures')->name('collections.structures');
        Route::view('/admin/authorization', 'authorization.index')->name('authorization.index');
        Route::get('/admin/users', [TenantUserController::class, 'index'])->name('admin.users');
        Route::get('/admin/users/{membership}', [TenantUserController::class, 'show'])->name('admin.users.show');
        Route::patch('/admin/users/{membership}/role', [TenantUserController::class, 'updateRole'])->name('admin.users.role');
        Route::patch('/admin/users/{membership}/status', [TenantUserController::class, 'updateStatus'])->name('admin.users.status');
        Route::post('/admin/users/{membership}/transfer-ownership', [TenantUserController::class, 'transfer'])->name('admin.users.transfer');
        Route::get('/admin/invitations', [TenantInvitationController::class, 'index'])->name('admin.invitations');
        Route::post('/admin/invitations', [TenantInvitationController::class, 'store'])->name('admin.invitations.store');
        Route::post('/admin/invitations/{invitation}/resend', [TenantInvitationController::class, 'resend'])->name('admin.invitations.resend');
        Route::delete('/admin/invitations/{invitation}', [TenantInvitationController::class, 'revoke'])->name('admin.invitations.revoke');
        Route::get('/admin/settings/company', [CompanySettingsController::class, 'show'])->name('admin.company');
        Route::patch('/admin/settings/company', [CompanySettingsController::class, 'update'])->name('admin.company.update');
        Route::get('/admin/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/admin/customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('/admin/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/admin/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::get('/admin/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::patch('/admin/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::patch('/admin/customers/{customer}/status', [CustomerController::class, 'disable'])->name('customers.status');
        Route::post('/admin/customers/{customer}/addresses', [CustomerController::class, 'address'])->name('customers.addresses.store');
        Route::patch('/admin/customers/{customer}/addresses/{address}', [CustomerController::class, 'updateAddress'])->name('customers.addresses.update');
        Route::delete('/admin/customers/{customer}/addresses/{address}', [CustomerController::class, 'deleteAddress'])->name('customers.addresses.delete');
        Route::get('/admin/products', [CatalogController::class, 'index'])->name('products.index');
        Route::get('/admin/products/create', [CatalogController::class, 'create'])->name('products.create');
        Route::post('/admin/products', [CatalogController::class, 'store'])->name('products.store');
        Route::get('/admin/products/{product}', [CatalogController::class, 'show'])->name('products.show');
        Route::get('/admin/products/{product}/edit', [CatalogController::class, 'edit'])->name('products.edit');
        Route::patch('/admin/products/{product}', [CatalogController::class, 'update'])->name('products.update');
        Route::post('/admin/products/categories', [CatalogController::class, 'category'])->name('products.categories.store');
        Route::patch('/admin/products/categories/{category}', [CatalogController::class, 'updateCategory'])->name('products.categories.update');
        Route::post('/admin/products/lists', [CatalogController::class, 'list'])->name('products.lists.store');
        Route::patch('/admin/products/lists/{list}', [CatalogController::class, 'updateList'])->name('products.lists.update');
        Route::post('/admin/products/{product}/prices', [CatalogController::class, 'price'])->name('products.prices.store');
        Route::post('/admin/products/{product}/costs', [CatalogController::class, 'cost'])->name('products.costs.store');
        Route::patch('/admin/products/{product}/prices/{price}', [CatalogController::class, 'closePrice'])->name('products.prices.close');
        Route::patch('/admin/products/{product}/costs/{cost}', [CatalogController::class, 'closeCost'])->name('products.costs.close');
        Route::get('/admin/quotations', [QuotationController::class, 'index'])->name('quotations.index');
        Route::get('/admin/quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
        Route::post('/admin/quotations', [QuotationController::class, 'store'])->name('quotations.store');
        Route::get('/admin/quotations/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
        Route::get('/admin/quotations/{quotation}/edit', [QuotationController::class, 'edit'])->name('quotations.edit');
        Route::patch('/admin/quotations/{quotation}', [QuotationController::class, 'update'])->name('quotations.update');
        Route::post('/admin/quotations/{quotation}/transition', [QuotationController::class, 'transition'])->name('quotations.transition');
        Route::post('/admin/quotations/{quotation}/duplicate', [QuotationController::class, 'duplicate'])->name('quotations.duplicate');
        Route::post('/admin/quotations/{quotation}/activities', [QuotationController::class, 'activity'])->name('quotations.activities.store');
        Route::get('/admin/quotations/{quotation}/print', [QuotationController::class, 'print'])->name('quotations.print');
        Route::get('/admin/quotations/{quotation}/pdf', [QuotationController::class, 'pdf'])->name('quotations.pdf');
        Route::get('/admin/sale-applications', [SaleController::class, 'applications'])->name('sale-applications.index');
        Route::get('/admin/sale-applications/direct', [SaleController::class, 'createDirect'])->name('sale-applications.direct');
        Route::post('/admin/sale-applications/direct', [SaleController::class, 'storeDirect'])->name('sale-applications.direct.store');
        Route::post('/admin/sale-applications/from-quotation/{quotation}', [SaleController::class, 'fromQuotation'])->name('sale-applications.from-quotation');
        Route::get('/admin/sale-applications/{application}', [SaleController::class, 'application'])->name('sale-applications.show');
        Route::patch('/admin/sale-applications/{application}/items', [SaleController::class, 'replaceDirectItems'])->name('sale-applications.items');
        Route::patch('/admin/sale-applications/{application}/terms', [SaleController::class, 'updateTerms'])->name('sale-applications.terms');
        Route::post('/admin/sale-applications/{application}/submit', [SaleController::class, 'submit'])->name('sale-applications.submit');
        Route::post('/admin/sale-applications/{application}/assess', [SaleController::class, 'assess'])->name('sale-applications.assess');
        Route::post('/admin/sale-applications/{application}/documents', [SaleController::class, 'uploadDocument'])->name('sale-applications.documents');
        Route::patch('/admin/customer-documents/{document}/verify', [SaleController::class, 'verifyDocument'])->name('customer-documents.verify');
        Route::get('/admin/customer-documents/{document}/download', [SaleController::class, 'document'])->name('customer-documents.download');
        Route::post('/admin/sale-applications/{application}/confirm', [SaleController::class, 'confirm'])->name('sale-applications.confirm');
        Route::get('/admin/sales', [SaleController::class, 'sales'])->name('sales.index');
        Route::get('/admin/sales/{sale}', [SaleController::class, 'sale'])->name('sales.show');
        Route::post('/admin/sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');
        Route::get('/admin/sale-documents/{document}/download', [SaleController::class, 'saleDocument'])->name('sale-documents.download');
        Route::get('/admin/customers/{customer}/account', [SaleController::class, 'account'])->name('customers.account');
        Route::get('/admin/customers/{customer}/documents', [SaleController::class, 'customerDocuments'])->name('customers.documents');
        Route::post('/admin/customers/{customer}/documents', [SaleController::class, 'uploadCustomerDocument'])->name('customers.documents.store');
        Route::get('/admin/late-fee-policies', [SaleController::class, 'policies'])->name('late-fee-policies.index');
        Route::post('/admin/late-fee-policies', [SaleController::class, 'storePolicy'])->name('late-fee-policies.store');
        Route::get('/modules/collectors', function () {
            Gate::authorize('routes.view');
            $tenant = request()->attributes->get('activeTenant');
            $collectors = TenantMembership::forTenant($tenant)->with('user')->where('status', 'active')->where('role', 'collector')->orderBy('joined_at')->get();
            $assignments = CustomerCollectorAssignment::forTenant($tenant)->whereNull('unassigned_at')->selectRaw('collector_membership_id, COUNT(*) AS customers_count')->groupBy('collector_membership_id')->pluck('customers_count', 'collector_membership_id');
            $routes = CollectionRoute::forTenant($tenant)->whereDate('route_date', today($tenant->timezone))->whereIn('status', ['published', 'in_progress'])->selectRaw('collector_membership_id, COUNT(*) AS routes_count')->groupBy('collector_membership_id')->pluck('routes_count', 'collector_membership_id');
            $collectors->each(function (TenantMembership $collector) use ($assignments, $routes): void {
                $collector->setAttribute('customers_count', $assignments[$collector->id] ?? 0);
                $collector->setAttribute('routes_count', $routes[$collector->id] ?? 0);
            });

            return view('modules.collectors', compact('collectors'));
        })->name('modules.collectors');
        Route::get('/modules/{section}', function (string $section) {
            $permissions = ['sales' => 'sales.view', 'collections' => 'collections.view', 'routes' => 'routes.view', 'collectors' => 'routes.view', 'settlements' => 'settlements.view', 'reports' => 'reports.view'];
            abort_unless(isset($permissions[$section]), 404);
            Gate::authorize($permissions[$section]);

            return view('modules.placeholder', compact('section'));
        })->name('modules.show');
    });
});
