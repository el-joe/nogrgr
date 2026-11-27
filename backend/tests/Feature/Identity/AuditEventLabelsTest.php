<?php

use App\Modules\Identity\Services\AuditLogService;
use App\Modules\Identity\Support\AuditPresenter;
use App\Modules\Platform\Services\Auditor;

/*
| QA-FINAL-011: HTTP fallback audit events get translated "<resource>: <verb>" labels and
| the admin "Event" filter never shows raw route / action names.
*/

it('labels request events by resource and verb in en and ar', function (): void {
    expect(AuditPresenter::actionLabel('http.admin.catalog.products.store'))->toBe('Products: created')
        ->and(AuditPresenter::actionLabel('http.vendor-central.settings.staff.destroy'))->toEndWith(': deleted')
        ->and(AuditPresenter::actionLabel('http.admin.system.cache.clear'))->toBe('Cache: cleared')
        ->and(AuditPresenter::actionLabel('http.admin.dashboard'))->not->toContain('admin.');

    app()->setLocale('ar');

    expect(AuditPresenter::actionLabel('http.admin.system.cache.clear'))->toBe('التخزين المؤقت: مسح');
});

it('lists the event filter with labels only', function (): void {
    foreach (['http.admin.catalog.products.store', 'http.admin.catalog.products.update'] as $action) {
        app(Auditor::class)->record($action);
    }

    $options = app(AuditLogService::class)->eventOptions();

    expect($options)->toHaveKey('http.admin.catalog.products.store')
        ->and(implode(' ', $options))->not->toContain('http.')
        ->and($options['http.admin.catalog.products.store'])->toBe('Products: created');
});
