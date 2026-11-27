<?php

use Illuminate\Support\Str;

require_once dirname(__DIR__).'/Commerce/CommerceHelpers.php';

/*
| RQA-VEN-003: a remote-modal form opened by URL (no X-Requested-With) is framed by the
| shell of its surface — the client Tenant Panel shell <x-vendor::layout> (vnd-shell,
| decision #101) on the vendor panel, the shared <x-layouts.panel> on admin.
*/

it('frames a vendor remote-modal page in the client vendor shell', function (): void {
    $tenant = $this->createTenant('rmp-'.Str::lower(Str::random(6)));
    $owner = $this->createVendorOwner($tenant, ['email' => 'owner-'.Str::lower(Str::random(6)).'@megz.test']);

    $this->actingAsVendor($owner, $tenant)
        ->get($this->tenantUrl($tenant, 'admin/marketing/coupons/create'))
        ->assertOk()
        ->assertSee('ui-panel vnd-shell', false)
        ->assertSee('class="vnd-main', false)
        ->assertSee('data-remote-modal-page', false);
});

it('keeps the shared panel shell for an admin remote-modal page', function (): void {
    $this->actingAs(commerceAdmin(), 'admin')
        ->get($this->centralUrl('/admin/promotions/coupons/create'))
        ->assertOk()
        ->assertSee('data-remote-modal-page', false)
        ->assertDontSee('vnd-shell', false);
});
