<?php

use App\Modules\Platform\Enums\TenantStatus;
use App\Shared\Tenancy\CentralDomains;

require_once __DIR__.'/../Commerce/StoreApiHelpers.php';

/*
| Themed store error / status pages (sf-checkout, SF-ECO-036, SF-ALL-001..004).
*/

it('renders the themed 404 on a store host', function (): void {
    $shop = shopSetup();

    $this->get($this->tenantUrl($shop->tenant, '/no-such-page-here'))
        ->assertNotFound()
        ->assertSee('This page doesn’t exist', false)
        ->assertSee('data-theme-family="ecommet"', false)
        ->assertSee('Continue shopping');
});

it('renders the themed 404 in arabic', function (): void {
    $shop = shopSetup();

    $this->get($this->tenantUrl($shop->tenant, '/no-such-page-here?lang=ar'))
        ->assertNotFound()
        ->assertSee('هذه الصفحة غير موجودة');
});

it('shows "Opening soon" for a store that is not live yet and keeps the newsletter open', function (): void {
    $shop = shopSetup();
    $shop->tenant->forceFill(['status' => TenantStatus::Provisioning])->save();

    $this->get($this->tenantUrl($shop->tenant, '/'))
        ->assertOk()
        ->assertSee('Opening shortly')
        ->assertSee('/newsletter"', false);
});

it('shows the suspended page with a 503 for a suspended store', function (): void {
    $shop = shopSetup();
    $shop->tenant->forceFill(['status' => TenantStatus::Suspended])->save();

    $this->get($this->tenantUrl($shop->tenant, '/cart'))
        ->assertStatus(503)
        ->assertSee('This store is temporarily unavailable', false);
});

it('renders the themed 500, 419, 403 and maintenance views', function (string $view, string $text): void {
    $shop = shopSetup();
    tenancy()->initialize($shop->tenant);

    $html = view('storefront::themes.ecommet.pages.errors.'.$view)->render();

    expect($html)->toContain($text)->toContain('Powered by');
})->with([
    '500' => ['500', 'Something went wrong'],
    '419' => ['419', 'Your session expired'],
    '403' => ['403', 'You don’t have access to this page'],
    'maintenance' => ['maintenance', 'We’ll be back shortly'],
]);

it('keeps the central website 404 on the central host', function (): void {
    $this->get('http://'.CentralDomains::primary().'/no-such-page-here')
        ->assertNotFound()
        ->assertDontSee('This page doesn’t exist', false);
});
