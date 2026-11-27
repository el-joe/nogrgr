<?php

use App\Modules\Notifications\Http\Controllers\Vendor\EmailTemplateController;
use App\Modules\Notifications\Http\Controllers\Vendor\MailSettingsController;
use App\Modules\Notifications\Http\Controllers\Vendor\NotificationPreferenceController;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor panel (both host modes) — link with vendor_route():
|   vendor.settings.email-templates.*   store-scoped e-mail templates (VEN-080 / 080a, #118), {event} =
|                                       EmailTemplateEvent value; settings.email-templates.view | manage
|   vendor.settings.mail*               store SMTP (VEN-081); settings.mail.view | manage
*/

Route::middleware('auth:vendor')->prefix('settings/email-templates')->name('settings.email-templates.')->controller(EmailTemplateController::class)->group(function (): void {
    Route::middleware('permission:settings.email-templates.view,vendor')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('data', 'data')->name('data');
        Route::get('{event}/edit', 'edit')->where('event', '[a-z_]+\.[a-z_]+')->name('edit');
        Route::get('{event}/preview', 'preview')->where('event', '[a-z_]+\.[a-z_]+')->name('preview');
    });
    Route::middleware('permission:settings.email-templates.manage,vendor')->group(function (): void {
        Route::post('sync', 'sync')->name('sync');
        Route::put('{event}', 'update')->where('event', '[a-z_]+\.[a-z_]+')->name('update');
        Route::delete('{event}', 'reset')->where('event', '[a-z_]+\.[a-z_]+')->name('reset');
        Route::post('{event}/render', 'render')->where('event', '[a-z_]+\.[a-z_]+')->middleware('throttle:120,1')->name('render');
        Route::post('{event}/test', 'test')->where('event', '[a-z_]+\.[a-z_]+')->middleware('throttle:10,1')->name('test');
    });
});

Route::middleware('auth:vendor')->prefix('settings/mail')->controller(MailSettingsController::class)->group(function (): void {
    Route::get('/', 'index')->middleware('permission:settings.mail.view,vendor')->name('settings.mail');
    Route::middleware('permission:settings.mail.manage,vendor')->name('settings.mail.')->group(function (): void {
        Route::put('/', 'update')->name('update');
        Route::delete('/', 'destroy')->name('destroy');
        Route::post('test', 'test')->middleware('throttle:10,1')->name('test');
    });
});

/*
| vendor.settings.notifications*   the member's own notification preferences (VEN-087); notifications.view
*/
Route::middleware(['auth:vendor', 'permission:notifications.view,vendor'])->prefix('settings/notifications')->controller(NotificationPreferenceController::class)->group(function (): void {
    Route::get('/', 'edit')->name('settings.notifications');
    Route::put('/', 'update')->name('settings.notifications.update');
});
