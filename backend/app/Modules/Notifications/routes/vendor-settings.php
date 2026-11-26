<?php

use App\Modules\Notifications\Http\Controllers\Vendor\EmailTemplateController;
use App\Modules\Notifications\Http\Controllers\Vendor\MailSettingsController;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor panel (both host modes) — link with vendor_route():
|   vendor.settings.email-templates.*   shopper e-mail templates (VEN-080 / 080a), {event} =
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
