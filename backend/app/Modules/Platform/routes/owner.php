<?php

use App\Modules\Platform\Http\Controllers\Owner\AddStoreController;
use App\Modules\Platform\Http\Controllers\Owner\OwnerSessionController;
use Illuminate\Support\Facades\Route;

/*
| Surface: owner portal — {central}/my-account, names "owner.*" (guard "owner").
| Every route: auth:owner + a gate (tests/Feature/Architecture/PanelRoutesTest.php).
*/

Route::middleware(['auth:owner', 'can:owner.stores.create'])->group(function (): void {
    Route::get('stores/create', [AddStoreController::class, 'create'])->name('stores.create');
    Route::post('stores', [AddStoreController::class, 'store'])->middleware('throttle:10,1')->name('stores.store');
    Route::get('stores/availability', [AddStoreController::class, 'availability'])->middleware('throttle:30,1')->name('stores.availability');
});

Route::post('logout', OwnerSessionController::class)->middleware('auth:owner')->name('logout');
