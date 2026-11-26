<?php

use App\Modules\Identity\Http\Controllers\Vendor\CustomerAddressController;
use App\Modules\Identity\Http\Controllers\Vendor\CustomerController;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor panel (both host modes) — link with vendor_route():
|   vendor.sales.customers.*             customers (VEN-025 / 025a / 025b), {customer} =
|                                        customer id (tenant DB)
|   vendor.sales.customers.addresses.*   address modal (VEN-025c), {address} = address id
|   vendor.sales.customers.cities        city options of a store country (address modal)
*/

Route::middleware('auth:vendor')->prefix('sales/customers')->name('sales.customers.')->group(function (): void {
    Route::controller(CustomerController::class)->group(function (): void {
        Route::middleware('permission:customers.view,vendor')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
        });
        Route::get('export', 'export')->middleware('permission:customers.export,vendor')->name('export');
        Route::middleware('permission:customers.manage,vendor')->group(function (): void {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('cities', 'cities')->name('cities');
            Route::get('{customer}/edit', 'edit')->whereNumber('customer')->name('edit');
            Route::put('{customer}', 'update')->whereNumber('customer')->name('update');
            Route::patch('{customer}/active', 'toggleActive')->whereNumber('customer')->name('toggle-active');
            Route::delete('{customer}', 'destroy')->whereNumber('customer')->name('destroy');
        });
        Route::middleware('permission:customers.view,vendor')->group(function (): void {
            Route::get('{customer}', 'show')->whereNumber('customer')->name('show');
            Route::get('{customer}/orders', 'orders')->whereNumber('customer')->name('orders');
        });
    });

    Route::prefix('{customer}/addresses')->whereNumber('customer')->name('addresses.')->controller(CustomerAddressController::class)
        ->middleware('permission:customers.manage,vendor')->group(function (): void {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{address}/edit', 'edit')->whereNumber('address')->name('edit');
            Route::put('{address}', 'update')->whereNumber('address')->name('update');
            Route::post('{address}/default', 'makeDefault')->whereNumber('address')->name('default');
            Route::delete('{address}', 'destroy')->whereNumber('address')->name('destroy');
        });
});
