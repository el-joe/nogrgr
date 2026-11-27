<?php

use App\Shared\Support\RelativeUrl;

/*
| QA-FINAL-008: in-app notification links are stored / resolved host-independent — our
| own panel URLs become paths, links to other hosts stay absolute.
*/

beforeEach(function (): void {
    config(['tenancy.central_domains' => ['nogrgr.test', 'www.nogrgr.test'], 'app.url' => 'https://nogrgr.test']);
});

it('shortens links of the central host and of the app url', function (): void {
    expect(RelativeUrl::of('https://nogrgr.test/admin/orders/5?tab=items#notes'))->toBe('/admin/orders/5?tab=items#notes')
        ->and(RelativeUrl::of('http://www.nogrgr.test/my-account/stores'))->toBe('/my-account/stores')
        ->and(RelativeUrl::of('https://nogrgr.test'))->toBe('/');
});

it('shortens old panel links whose host no longer exists', function (): void {
    expect(RelativeUrl::of('http://127.0.0.1:8099/admin/vendors/3'))->toBe('/admin/vendors/3')
        ->and(RelativeUrl::of('http://localhost:8097/tenant/acme/sales/orders'))->toBe('/tenant/acme/sales/orders')
        ->and(RelativeUrl::of('http://localhost:8097/affiliate/payouts'))->toBe('/affiliate/payouts');
});

it('keeps other hosts, relative paths and empty values', function (): void {
    expect(RelativeUrl::of('https://acme.example.com/products/shoe'))->toBe('https://acme.example.com/products/shoe')
        ->and(RelativeUrl::of('https://checkout.stripe.com/pay/cs_test'))->toBe('https://checkout.stripe.com/pay/cs_test')
        ->and(RelativeUrl::of('/admin/orders'))->toBe('/admin/orders')
        ->and(RelativeUrl::of('mailto:help@nogrgr.test'))->toBe('mailto:help@nogrgr.test')
        ->and(RelativeUrl::of(''))->toBeNull()
        ->and(RelativeUrl::of(null))->toBeNull();
});
