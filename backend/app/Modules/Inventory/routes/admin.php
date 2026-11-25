<?php

use App\Modules\Inventory\Http\Controllers\Admin\BranchController;
use Illuminate\Support\Facades\Route;

/*
| Surface: admin — {central}/admin/shipping/branches, names "admin.shipping.branches.*".
*/

Route::middleware('auth:admin')->prefix('shipping/branches')->name('shipping.branches.')->controller(BranchController::class)->group(function (): void {
    Route::middleware('permission:shipping.branches.view,admin')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('data', 'data')->name('data');
        Route::get('{branch}/stock', 'stock')->whereNumber('branch')->name('stock');
    });

    Route::middleware('permission:shipping.branches.manage,admin')->group(function (): void {
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('cities', 'cities')->name('cities');
        Route::get('zones', 'zones')->name('zones');
        Route::get('{branch}/edit', 'edit')->whereNumber('branch')->name('edit');
        Route::put('{branch}', 'update')->whereNumber('branch')->name('update');
        Route::delete('{branch}', 'destroy')->whereNumber('branch')->name('destroy');
        Route::post('{branch}/stock/import', 'import')->whereNumber('branch')->name('stock.import');
    });
});
