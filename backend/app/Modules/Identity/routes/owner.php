<?php

use App\Modules\Identity\Http\Controllers\Owner\OwnerProfileController;
use Illuminate\Support\Facades\Route;

/*
| Surface: owner portal — Profile (OWN-006), names "owner.profile.*".
| Every route: auth:owner + can:owner.portal (the signed-in owner edits only themself).
*/

Route::middleware(['auth:owner', 'can:owner.portal'])->prefix('profile')->name('profile.')->controller(OwnerProfileController::class)->group(function (): void {
    Route::get('/', 'edit')->name('edit');
    Route::put('/', 'update')->middleware('throttle:20,1')->name('update');
    Route::put('password', 'password')->middleware('throttle:10,1')->name('password');
    Route::post('social/{provider}', 'link')->where('provider', 'google|apple')->middleware('throttle:10,1')->name('social.link');
    Route::delete('social/{provider}', 'unlink')->where('provider', 'google|apple')->middleware('throttle:10,1')->name('social.unlink');
});
