<?php

use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Services\VendorAuditLogService;
use App\Modules\Platform\Models\AuditEvent;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Support\Str;

/*
| VEN-086 Vendor › Settings › Audit log: settings.audit.view, the store's own team events
| only (staff, Nogrgr support incl. impersonation, system — never shoppers or other stores),
| member / area / search filters, masked IPs.
*/

function storeAuditEvent(Tenant $tenant, array $attributes): AuditEvent
{
    return AuditEvent::query()->withoutGlobalScopes()->create(array_merge([
        'tenant_id' => $tenant->id,
        'guard' => 'vendor',
        'actor_type' => (new VendorStaff)->getMorphClass(),
        'action' => 'product.updated',
        'ip_address' => '37.120.4.12',
    ], $attributes));
}

beforeEach(function (): void {
    $this->tenant = $this->createTenant('aud-'.Str::lower(Str::random(6)));
    $this->owner = $this->createVendorOwner($this->tenant, ['name' => 'Mostafa Gamal', 'email' => 'owner-'.Str::lower(Str::random(6)).'@megz.test']);
    $this->manager = $this->tenant->run(function (): VendorStaff {
        $member = VendorStaff::factory()->create(['name' => 'Hala Saeed']);
        $member->givePermissionTo(['dashboard.view', 'settings.audit.view']);

        return $member;
    });
    $this->admin = Admin::factory()->create(['name' => 'Sara']);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/settings/audit'.$path);
    $this->rows = fn (array $query = []): array => $this->getJson(($this->url)('/data?'.http_build_query(array_merge(['draw' => 1, 'start' => 0, 'length' => 10], $query))))->assertOk()->json('data');
});

it('guards the log by the settings.audit.view permission', function (): void {
    $this->get(($this->url)())->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    $none = $this->tenant->run(function (): VendorStaff {
        $none = VendorStaff::factory()->create();
        $none->givePermissionTo('dashboard.view');

        return $none;
    });
    $this->actingAsVendor($none, $this->tenant);
    $this->get(($this->url)())->assertForbidden();
    $this->getJson(($this->url)('/data'))->assertForbidden();

    $this->actingAsVendor($this->manager, $this->tenant);
    $this->get(($this->url)())->assertOk()->assertSee(__('identity::vendor_audit.title'))->assertSee('Hala Saeed');
});

it('lists the store team events only, with members, impersonation and masked IPs', function (): void {
    $other = $this->createTenant('aud-o-'.Str::lower(Str::random(6)));
    storeAuditEvent($this->tenant, ['actor_id' => $this->manager->id, 'action' => 'product.updated']);
    storeAuditEvent($this->tenant, ['actor_id' => $this->owner->id, 'action' => 'auth.login', 'changes' => ['impersonator_admin_id' => $this->admin->id]]);
    storeAuditEvent($this->tenant, ['guard' => 'customer', 'actor_type' => 'customer', 'actor_id' => 5, 'action' => 'order.created']);
    storeAuditEvent($other, ['actor_id' => 1, 'action' => 'coupon.created']);

    $this->actingAsVendor($this->manager, $this->tenant);
    $rows = collect(($this->rows)());

    expect($rows)->toHaveCount(2);
    $html = $rows->map(fn (array $row): string => $row['member'].' '.$row['action'].' '.$row['ip_address'])->join(' ');
    expect($html)->toContain('Hala Saeed')
        ->toContain(e(__('identity::vendor_audit.support_name', ['name' => 'Sara'])))
        ->toContain(__('identity::vendor_audit.impersonation'))
        ->toContain('37.x.x.12')
        ->not->toContain('37.120.4.12')
        ->not->toContain('Coupon');
});

it('filters by member, area and search', function (): void {
    storeAuditEvent($this->tenant, ['actor_id' => $this->manager->id, 'action' => 'product.updated']);
    storeAuditEvent($this->tenant, ['actor_id' => $this->owner->id, 'action' => 'store.settings_updated']);

    $this->actingAsVendor($this->manager, $this->tenant);

    expect(($this->rows)(['actor' => $this->owner->id]))->toHaveCount(1)
        ->and(($this->rows)(['area' => 'product']))->toHaveCount(1)
        ->and(($this->rows)(['area' => 'bad area!']))->toHaveCount(2)
        ->and(($this->rows)(['search' => 'settings_updated']))->toHaveCount(1);

    $this->get(($this->url)())->assertOk()->assertSee(__('identity::vendor_audit.areas.store'));
});

it('records the vendor settings changes in the store log', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);
    $this->putJson($this->tenantUrl($this->tenant, 'admin/settings/account'), ['name' => 'Mostafa G', 'phone' => '0501234567', 'phone_country' => 'SA', 'locale' => 'en'])->assertOk();

    $rows = collect(($this->rows)(['area' => 'account']));
    expect($rows)->toHaveCount(1)->and($rows->first()['action'])->toContain(__('identity::audit.actions.account.updated'));
});

it('masks IPv4 and IPv6 addresses', function (): void {
    expect(VendorAuditLogService::maskIp('5.1.2.80'))->toBe('5.x.x.80')
        ->and(VendorAuditLogService::maskIp('2001:db8:85a3::8a2e:370:7334'))->toBe('2001:db8::x')
        ->and(VendorAuditLogService::maskIp(null))->toBe('—');
});
