<?php

use App\Http\Controllers\ApiContextController;
use App\Http\Controllers\CommercialApiController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['data' => ['status' => 'ok']]));
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', [ApiContextController::class, 'me']);
    Route::get('/tenants', [ApiContextController::class, 'tenants']);
    Route::post('/tenant/select', [ApiContextController::class, 'select']);
    Route::get('/permissions', [ApiContextController::class, 'permissions']);
    Route::get('/profile', [ApiContextController::class, 'profile']);
    Route::middleware('tenant.api')->group(function (): void {
        Route::get('/customers', [CommercialApiController::class, 'customers']);
        Route::get('/customers/{customer}', [CommercialApiController::class, 'customer']);
        Route::get('/customers/{customer}/addresses', [CommercialApiController::class, 'addresses']);
        Route::get('/products', [CommercialApiController::class, 'products']);
        Route::get('/products/{product}', [CommercialApiController::class, 'product']);
        Route::get('/quotations', [CommercialApiController::class, 'quotations']);
        Route::get('/quotations/{quotation}', [CommercialApiController::class, 'quotation']);
    });
});
