<?php

use App\Modules\Localization\Models\Country;
use App\Modules\Shipping\Models\DeliveryPopupDay;
use App\Modules\Shipping\Models\ShippingCompany;
use App\Modules\Shipping\Services\DeliveryPopupService;
use App\Modules\Shipping\Services\ShippingSettingsService;
use Carbon\CarbonImmutable;

require_once __DIR__.'/ShippingHelpers.php';

/*
| Delivery popup days (ADM-035) and shipping settings (ADM-034).
*/

function popupDays(array $values): array
{
    return array_combine(range(1, 10), $values);
}

it('guards the popup and settings pages', function (): void {
    $this->get($this->centralUrl('/admin/shipping/delivery-popup'))->assertRedirect($this->centralUrl('/admin/login'));
    $this->get($this->centralUrl('/admin/shipping/settings'))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(shippingAdmin([]), 'admin');
    $this->getJson($this->centralUrl('/admin/shipping/delivery-popup'))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/shipping/settings'))->assertForbidden();

    $this->actingAs(shippingAdmin(['shipping.delivery-popup.view', 'shipping.settings.view']), 'admin');
    $this->get($this->centralUrl('/admin/shipping/delivery-popup'))->assertOk()->assertSee('days[10]', false);
    $this->get($this->centralUrl('/admin/shipping/settings'))->assertOk()->assertSee('default_min_days', false);
    $this->putJson($this->centralUrl('/admin/shipping/delivery-popup'), [])->assertForbidden();
    $this->deleteJson($this->centralUrl('/admin/shipping/delivery-popup'))->assertForbidden();
    $this->putJson($this->centralUrl('/admin/shipping/settings'), [])->assertForbidden();
    $this->postJson($this->centralUrl('/admin/shipping/settings/defaults'), [])->assertForbidden();
});

it('saves a distribution only when it adds up to exactly 100 %', function (): void {
    $this->actingAs(shippingAdmin(), 'admin');

    $this->putJson($this->centralUrl('/admin/shipping/delivery-popup'), ['days' => popupDays([0, 5, 10, 30, 25, 10, 5, 5, 0, 0])])
        ->assertJsonValidationErrors(['days' => __('shipping::popup.sum_invalid', ['total' => 90])]);
    $this->putJson($this->centralUrl('/admin/shipping/delivery-popup'), ['days' => popupDays([0, 5, 20, 30, 25, 10, 5, 5, 0, '1.5'])])->assertJsonValidationErrors(['days.10']);
    $this->putJson($this->centralUrl('/admin/shipping/delivery-popup'), ['days' => array_combine(range(0, 9), [0, 5, 20, 30, 25, 10, 5, 5, 0, 0])])->assertJsonValidationErrors(['days']);
    $this->putJson($this->centralUrl('/admin/shipping/delivery-popup'), ['days' => [1 => 100]])->assertJsonValidationErrors(['days']);

    $this->putJson($this->centralUrl('/admin/shipping/delivery-popup'), ['days' => popupDays([0, 5, 20, 30, 25, 10, 5, 5, 0, 0])])->assertOk();
    expect(DeliveryPopupDay::query()->where('country_key', 0)->count())->toBe(10);
});

it('stores per-country distributions, hides 0 % days on the storefront and resets', function (): void {
    $this->actingAs(shippingAdmin(), 'admin');
    $sa = Country::factory()->iso('SA')->create();
    $eg = Country::factory()->iso('EG')->create();

    $this->putJson($this->centralUrl('/admin/shipping/delivery-popup'), ['country_id' => $sa->id, 'days' => popupDays([50, 50, 0, 0, 0, 0, 0, 0, 0, 0])])->assertOk();

    $this->get($this->centralUrl("/admin/shipping/delivery-popup?country={$sa->id}"))->assertOk()->assertSee(__('shipping::popup.scope_own', ['country' => $sa->name]));

    $popup = app(DeliveryPopupService::class);
    $rows = $popup->forStorefront($sa->id, CarbonImmutable::parse('2026-11-24 09:00'));
    expect($rows)->toBe([['day' => 1, 'percentage' => 50, 'date' => '2026-11-25'], ['day' => 2, 'percentage' => 50, 'date' => '2026-11-26']])
        ->and(array_column($popup->forStorefront($eg->id), 'day'))->toBe([2, 3, 4, 5, 6, 7, 8]);

    $this->deleteJson($this->centralUrl('/admin/shipping/delivery-popup'), ['country_id' => $sa->id])->assertOk();
    expect($popup->distribution($sa->id)['own'])->toBeFalse();
});

it('saves settings and default companies with validation', function (): void {
    $this->actingAs(shippingAdmin(), 'admin');
    $sa = Country::factory()->iso('SA')->create();
    $company = ShippingCompany::factory()->create(['name' => 'Aramex']);
    $inactive = ShippingCompany::factory()->inactive()->create();

    $this->putJson($this->centralUrl('/admin/shipping/settings'), ['default_min_days' => 5, 'default_max_days' => 2, 'cutoff_time' => '25:00', 'weekly_off_days' => [9], 'show_estimate_on_product' => '1', 'show_delivery_popup' => '1'])
        ->assertJsonValidationErrors(['default_max_days', 'cutoff_time', 'weekly_off_days.0']);

    $this->putJson($this->centralUrl('/admin/shipping/settings'), ['default_min_days' => 2, 'default_max_days' => 6, 'cutoff_time' => '14:00', 'weekly_off_days' => [5], 'show_estimate_on_product' => '1', 'show_delivery_popup' => '0'])->assertOk();
    $settings = app(ShippingSettingsService::class)->get();
    expect($settings->defaultMaxDays)->toBe(6)->and($settings->cutoffTime)->toBe('14:00')->and($settings->weeklyOffDays)->toBe([5])->and($settings->showDeliveryPopup)->toBeFalse();

    $this->postJson($this->centralUrl('/admin/shipping/settings/defaults'), ['country_id' => $sa->id, 'shipping_company_id' => $inactive->id])->assertJsonValidationErrors(['shipping_company_id']);
    $this->postJson($this->centralUrl('/admin/shipping/settings/defaults'), ['country_id' => $sa->id, 'shipping_company_id' => $company->id])->assertOk();
    $this->get($this->centralUrl('/admin/shipping/settings'))->assertOk()->assertSee('Aramex');

    $this->deleteJson($this->centralUrl("/admin/shipping/settings/defaults/{$sa->id}"))->assertOk();
    expect(app(ShippingSettingsService::class)->defaultsTable())->toBe([]);
});
