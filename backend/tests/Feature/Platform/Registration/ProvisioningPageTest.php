<?php

use App\Modules\Integrations\Repositories\Contracts\SettingsRepositoryContract;
use App\Modules\Integrations\Services\GeneralSettingsService;
use App\Modules\Platform\Enums\RegistrationStatus;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Models\VendorRegistration;
use App\Modules\Platform\Services\VendorRegistrationService;

require_once __DIR__.'/../Vendors/VendorHelpers.php';

/*
| WEB-020: signed provisioning page (progress / success / failure) and the status JSON
| it polls.
*/

/**
 * @return array{0: VendorRegistration, 1: Tenant}
 */
function provisioningRegistration(TenantStatus $status, array $tenantAttributes = []): array
{
    $tenant = vendorsTenant(array_merge(['status' => $status], $tenantAttributes));
    $registration = VendorRegistration::factory()->create([
        'status' => RegistrationStatus::Completed,
        'tenant_id' => $tenant->id,
        'slug' => $tenant->slug,
        'custom_domain' => 'shop.megzkids.com',
    ]);

    return [$registration, $tenant];
}

function statusUrl(VendorRegistration $registration): string
{
    return app(VendorRegistrationService::class)->provisioningStatusUrl($registration);
}

it('refuses an unsigned provisioning page or status', function (): void {
    [$registration] = provisioningRegistration(TenantStatus::Pending);

    $this->get(route('central.register.provisioning', $registration))->assertForbidden();
    $this->getJson(route('central.register.provisioning.status', $registration))->assertForbidden();
});

it('shows the progress state while the store is provisioned', function (): void {
    [$registration] = provisioningRegistration(TenantStatus::Provisioning);

    $this->get(app(VendorRegistrationService::class)->provisioningUrl($registration))
        ->assertOk()
        ->assertSee('data-phase="progress"', false)
        ->assertSee(__('platform::register.provisioning.title'))
        ->assertSee(__('platform::register.provisioning.database'))
        ->assertSee('data-provisioning-config', false);
});

it('keeps the signed page valid when the language is switched', function (): void {
    [$registration] = provisioningRegistration(TenantStatus::Pending);

    $this->get(app(VendorRegistrationService::class)->provisioningUrl($registration).'&lang=ar')
        ->assertOk()
        ->assertSee(__('platform::register.provisioning.title', [], 'ar'));
});

it('shows the store address and the panel link when the store is live', function (): void {
    [$registration, $tenant] = provisioningRegistration(TenantStatus::Active);

    $this->get(app(VendorRegistrationService::class)->provisioningUrl($registration))
        ->assertOk()
        ->assertSee('data-phase="success"', false)
        ->assertSee($tenant->subdomainHost())
        ->assertSee(route('central.tenant.login', ['store' => $tenant->slug]), false);
});

it('hides the technical error on failure and offers support', function (): void {
    app(SettingsRepositoryContract::class)->put(GeneralSettingsService::GROUP, ['support_email' => 'support@nogrgr.test']);
    [$registration] = provisioningRegistration(TenantStatus::Failed, ['provisioning_error' => 'SQLSTATE[HY000] secret detail']);

    $this->get(app(VendorRegistrationService::class)->provisioningUrl($registration))
        ->assertOk()
        ->assertSee('data-phase="failed"', false)
        ->assertSee(__('platform::register.provisioning.failed_text'))
        ->assertSee('mailto:support@nogrgr.test', false)
        ->assertDontSee('SQLSTATE');
});

it('hides the support link when General settings has no support e-mail (QA-P5-016)', function (): void {
    config(['mail.from.address' => 'no-reply@nogrgr.test']);
    [$registration] = provisioningRegistration(TenantStatus::Failed);

    $this->get(app(VendorRegistrationService::class)->provisioningUrl($registration))
        ->assertOk()
        ->assertSee('data-phase="failed"', false)
        ->assertDontSee('mailto:', false)
        ->assertDontSee('no-reply@nogrgr.test');
});

it('returns the status JSON for pending, active and failed stores', function (TenantStatus $status, bool $done, bool $failed): void {
    [$registration, $tenant] = provisioningRegistration($status);

    $this->getJson(statusUrl($registration))
        ->assertOk()
        ->assertExactJson([
            'status' => $status->value,
            'label' => $status->label(),
            'done' => $done,
            'failed' => $failed,
            'redirect' => $done ? route('central.tenant.login', ['store' => $tenant->slug]) : null,
        ]);
})->with([
    'pending' => [TenantStatus::Pending, false, false],
    'active' => [TenantStatus::Active, true, false],
    'failed' => [TenantStatus::Failed, false, true],
]);

it('returns 404 for a registration without a store', function (): void {
    $registration = VendorRegistration::factory()->create(['status' => RegistrationStatus::Incomplete]);

    $this->get(app(VendorRegistrationService::class)->provisioningUrl($registration))->assertNotFound();
});
