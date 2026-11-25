<?php

use App\Modules\Catalog\Http\Controllers\Admin\Requests\BrandRequestController;
use App\Modules\Catalog\Http\Controllers\Admin\Requests\EditRequestController;
use App\Modules\Catalog\Http\Controllers\Admin\Requests\ManufacturingRequestController;
use App\Modules\Catalog\Http\Controllers\Admin\Requests\ProductRequestController;
use App\Modules\Catalog\Http\Controllers\Admin\Requests\RequestAttachmentController;
use App\Modules\Catalog\Http\Controllers\Admin\Requests\RequestsCenterController;
use App\Modules\Catalog\Http\Controllers\Admin\Requests\TenantChangeRequestController;
use Illuminate\Support\Facades\Route;

/*
| Admin › Requests center (ADM-018…018i) — {central}/admin/requests, names "admin.requests.*".
| Each tab: requests.<type>.view reads, requests.<type>.manage acts (implies view).
| Literal segments (data) are declared before the {id} routes.
*/

$anyView = 'permission:requests.products.view|requests.edits.view|requests.manufacturing.view|requests.brands.view|requests.tenant-changes.view,admin';

Route::middleware('auth:admin')->prefix('requests')->name('requests.')->group(function () use ($anyView): void {
    Route::get('/', RequestsCenterController::class)->middleware($anyView)->name('index');
    Route::get('attachments/{attachment}', RequestAttachmentController::class)->whereNumber('attachment')
        ->middleware('permission:requests.products.view|requests.manufacturing.view|requests.brands.view,admin')->name('attachments.show');

    $threaded = [
        'products' => [ProductRequestController::class, 'requests.products'],
        'manufacturing' => [ManufacturingRequestController::class, 'requests.manufacturing'],
        'brands' => [BrandRequestController::class, 'requests.brands'],
    ];

    foreach ($threaded as $segment => [$controller, $permission]) {
        Route::controller($controller)->prefix($segment)->name($segment.'.')->group(function () use ($segment, $permission): void {
            Route::middleware("permission:{$permission}.view,admin")->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::get('data', 'data')->name('data');
                Route::get('{id}', 'show')->whereNumber('id')->name('show');
                Route::get('{id}/messages', 'messages')->whereNumber('id')->name('messages');
            });

            Route::middleware("permission:{$permission}.manage,admin")->group(function () use ($segment): void {
                Route::post('{id}/messages', 'reply')->whereNumber('id')->middleware('throttle:30,1')->name('reply');
                Route::patch('{id}/status', 'status')->whereNumber('id')->name('status');

                if ($segment === 'products') {
                    Route::patch('{id}/priority', 'priority')->whereNumber('id')->name('priority');
                    Route::patch('{id}/archive', 'archive')->whereNumber('id')->name('archive');
                } else {
                    Route::put('{id}/notes', 'notes')->whereNumber('id')->name('notes');
                    Route::post('{id}/payments', 'storePayment')->whereNumber('id')->name('payments.store');
                    Route::post('{id}/payments/{payment}/cancel', 'cancelPayment')->whereNumber(['id', 'payment'])->name('payments.cancel');
                }
            });
        });
    }

    Route::controller(EditRequestController::class)->prefix('edits')->name('edits.')->group(function (): void {
        Route::middleware('permission:requests.edits.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('{editRequest}', 'show')->whereNumber('editRequest')->name('show');
            Route::get('{editRequest}/files/{index}', 'file')->whereNumber(['editRequest', 'index'])->name('file');
        });

        Route::middleware('permission:requests.edits.manage,admin')->group(function (): void {
            Route::post('{editRequest}/approve', 'approve')->whereNumber('editRequest')->name('approve');
            Route::post('{editRequest}/reject', 'reject')->whereNumber('editRequest')->name('reject');
        });
    });

    Route::controller(TenantChangeRequestController::class)->prefix('tenant-changes')->name('tenant-changes.')->group(function (): void {
        Route::middleware('permission:requests.tenant-changes.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('{id}', 'show')->whereNumber('id')->name('show');
        });

        Route::post('{id}/decision', 'decide')->whereNumber('id')->middleware('permission:requests.tenant-changes.manage,admin')->name('decide');
    });
});
