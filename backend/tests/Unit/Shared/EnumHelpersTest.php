<?php

use App\Modules\Platform\Enums\TenantStatus;

it('lists values and options', function (): void {
    expect(TenantStatus::values())->toContain('active', 'pending')
        ->and(TenantStatus::options())->toHaveKey('active');
});

it('translates labels from the module namespace', function (): void {
    app()->setLocale('en');
    expect(TenantStatus::Active->label())->toBe('Active');

    app()->setLocale('ar');
    expect(TenantStatus::Active->label())->toBe('نشط');
});

it('derives the translation key from the module', function (): void {
    expect(TenantStatus::translationKey())->toBe('platform::enums.tenant_status');
});
