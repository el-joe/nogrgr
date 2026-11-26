<?php

use App\Modules\Platform\Http\Controllers\Admin\DeveloperDocsController;
use Illuminate\Support\Facades\Route;

/*
| Admin › System tools › Developer docs (ADM-115 / 115a).
*/

Route::middleware(['auth:admin', 'permission:system.docs.view,admin'])
    ->prefix('system/docs')
    ->name('system.docs.')
    ->controller(DeveloperDocsController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('{topic}', 'show')->name('show');
    });
