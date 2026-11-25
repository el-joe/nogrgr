<?php

use App\Modules\Platform\Http\Controllers\Admin\DnsRecordTemplateController;
use App\Modules\Platform\Http\Controllers\Admin\DomainRequestController;
use Illuminate\Support\Facades\Route;

/*
| Admin › Vendors › Domains & DNS (ADM-045 / 045a / 045b): custom-domain requests
| (list, detail, DNS check, connect / remove / reopen) and the platform DNS record
| templates shown to vendors. View: vendors.domains.view · actions: vendors.domains.manage.
| Literal segments (data, dns-records) are declared before the {domainRequest} routes.
*/

Route::middleware('auth:admin')->prefix('vendors')->name('vendors.domains.')->group(function (): void {
    Route::middleware('permission:vendors.domains.view,admin')->group(function (): void {
        Route::get('domains', [DomainRequestController::class, 'index'])->name('index');
        Route::get('domains/data', [DomainRequestController::class, 'data'])->name('data');
        Route::get('domains/dns-records/data', [DnsRecordTemplateController::class, 'data'])->name('dns.data');
        Route::get('domains/{domainRequest}', [DomainRequestController::class, 'show'])->whereNumber('domainRequest')->name('show');
    });

    Route::middleware('permission:vendors.domains.manage,admin')->group(function (): void {
        Route::post('{tenant}/domains', [DomainRequestController::class, 'store'])->whereNumber('tenant')->name('store');

        Route::post('domains/dns-records', [DnsRecordTemplateController::class, 'store'])->name('dns.store');
        Route::put('domains/dns-records/{dnsRecordTemplate}', [DnsRecordTemplateController::class, 'update'])->whereNumber('dnsRecordTemplate')->name('dns.update');
        Route::delete('domains/dns-records/{dnsRecordTemplate}', [DnsRecordTemplateController::class, 'destroy'])->whereNumber('dnsRecordTemplate')->name('dns.destroy');

        Route::post('domains/{domainRequest}/check', [DomainRequestController::class, 'check'])->whereNumber('domainRequest')->middleware('throttle:20,1')->name('check');
        Route::post('domains/{domainRequest}/connect', [DomainRequestController::class, 'connect'])->whereNumber('domainRequest')->name('connect');
        Route::post('domains/{domainRequest}/remove', [DomainRequestController::class, 'remove'])->whereNumber('domainRequest')->name('remove');
        Route::post('domains/{domainRequest}/reopen', [DomainRequestController::class, 'reopen'])->whereNumber('domainRequest')->name('reopen');
    });
});
