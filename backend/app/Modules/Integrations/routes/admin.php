<?php

use App\Modules\Integrations\Enums\IntegrationSection;
use App\Modules\Integrations\Http\Controllers\Admin\GeneralSettingsController;
use App\Modules\Integrations\Http\Controllers\Admin\IntegrationController;
use App\Modules\Integrations\Http\Controllers\Admin\PaymentGatewayController;
use Illuminate\Support\Facades\Route;

/*
| Surface: admin.
|  {central}/admin/integrations/...  names "admin.integrations.*"  (integrations.view / .manage)
|  {central}/admin/settings/general  names "admin.settings.general*" (settings.view / .manage)
| Connection tests call external services: throttled per admin.
*/

Route::middleware('auth:admin')->group(function (): void {
    Route::prefix('integrations')->name('integrations.')->group(function (): void {
        $sections = IntegrationSection::values();

        Route::middleware('permission:integrations.view,admin')->group(function () use ($sections): void {
            Route::get('/', [IntegrationController::class, 'index'])->name('index');
            Route::get('gateways', [PaymentGatewayController::class, 'index'])->name('gateways.index');
            Route::get('gateways/{gateway}/edit', [PaymentGatewayController::class, 'edit'])->name('gateways.edit');
            Route::get('{section}', [IntegrationController::class, 'show'])->whereIn('section', $sections)->name('show');
        });

        Route::middleware('permission:integrations.manage,admin')->group(function () use ($sections): void {
            Route::put('gateways/{gateway}', [PaymentGatewayController::class, 'update'])->name('gateways.update');
            Route::patch('gateways/{gateway}/active', [PaymentGatewayController::class, 'toggle'])->name('gateways.toggle');
            Route::post('gateways/{gateway}/test/{purpose}', [PaymentGatewayController::class, 'test'])->middleware('throttle:12,1')->name('gateways.test');
            Route::put('{section}', [IntegrationController::class, 'update'])->whereIn('section', $sections)->name('update');
            Route::post('{section}/test', [IntegrationController::class, 'test'])->whereIn('section', $sections)->middleware('throttle:12,1')->name('test');
        });
    });

    Route::prefix('settings/general')->name('settings.general')->controller(GeneralSettingsController::class)->group(function (): void {
        Route::get('/', 'edit')->middleware('permission:settings.view,admin')->name('');
        Route::put('/', 'update')->middleware('permission:settings.manage,admin')->name('.update');
    });
});
