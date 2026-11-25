<?php

use App\Modules\Localization\Models\Country;
use App\Modules\Shipping\Models\ShippingCompany;
use App\Modules\Shipping\Models\ShippingStopPeriod;

require_once __DIR__.'/ShippingHelpers.php';

/*
| Stop periods (ADM-033c): permissions, calendar + list + filters, create / edit / delete, validation.
*/

it('redirects guests and forbids admins without stop-period permissions', function (): void {
    $period = ShippingStopPeriod::query()->create(['name' => 'Eid', 'recurrence' => 'once', 'starts_on' => '2026-12-01', 'is_active' => true]);

    $this->get($this->centralUrl('/admin/shipping/stop-periods'))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(shippingAdmin([]), 'admin');
    $this->getJson($this->centralUrl('/admin/shipping/stop-periods'))->assertForbidden();

    $this->actingAs(shippingAdmin(['shipping.stop-periods.view']), 'admin');
    $this->get($this->centralUrl('/admin/shipping/stop-periods?month=2026-12'))->assertOk()->assertSee('Eid');
    $this->getJson($this->centralUrl('/admin/shipping/stop-periods/create'))->assertForbidden();
    $this->postJson($this->centralUrl('/admin/shipping/stop-periods'), [])->assertForbidden();
    $this->putJson($this->centralUrl("/admin/shipping/stop-periods/{$period->id}"), [])->assertForbidden();
    $this->deleteJson($this->centralUrl("/admin/shipping/stop-periods/{$period->id}"))->assertForbidden();
});

it('creates range and weekly periods, renders them on the calendar and filters by company', function (): void {
    $this->actingAs(shippingAdmin(['shipping.stop-periods.manage', 'shipping.companies.view']), 'admin');
    $aramex = ShippingCompany::factory()->create(['name' => 'Aramex']);
    $sa = Country::factory()->iso('SA')->create();

    $this->getJson($this->centralUrl("/admin/shipping/stop-periods/create?company={$aramex->id}"), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertSee('data-stop-form', false);

    $this->postJson($this->centralUrl('/admin/shipping/stop-periods'), ['name' => 'National day', 'shipping_company_id' => '', 'country_id' => $sa->id, 'recurrence' => 'once', 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-02', 'is_active' => '1'])->assertCreated();
    $this->postJson($this->centralUrl('/admin/shipping/stop-periods'), ['name' => 'Every Friday', 'shipping_company_id' => $aramex->id, 'recurrence' => 'weekly', 'weekday' => 5, 'starts_on' => '2026-12-01', 'is_active' => '1'])->assertCreated();
    $this->postJson($this->centralUrl('/admin/shipping/stop-periods'), ['name' => 'Other', 'shipping_company_id' => ShippingCompany::factory()->create()->id, 'recurrence' => 'once', 'starts_on' => '2026-12-09', 'is_active' => '1'])->assertCreated();

    $weekly = ShippingStopPeriod::query()->where('name', 'Every Friday')->sole();
    expect($weekly->weekday?->value)->toBe(5)->and($weekly->ends_on)->toBeNull();

    $this->get($this->centralUrl("/admin/shipping/stop-periods?month=2026-12&company={$aramex->id}"))
        ->assertOk()->assertSee('National day')->assertSee('Every Friday')->assertDontSee('>Other<', false);

    $this->get($this->centralUrl("/admin/shipping/companies/{$aramex->id}?tab=stop-periods"))->assertOk()->assertSee('Every Friday');

    $this->putJson($this->centralUrl("/admin/shipping/stop-periods/{$weekly->id}"), ['name' => 'Fridays', 'recurrence' => 'once', 'weekday' => 5, 'starts_on' => '2026-12-04', 'is_active' => '0'])->assertOk();
    expect($weekly->refresh()->weekday)->toBeNull()->and($weekly->is_active)->toBeFalse();

    $this->deleteJson($this->centralUrl("/admin/shipping/stop-periods/{$weekly->id}"))->assertOk();
    expect(ShippingStopPeriod::query()->count())->toBe(2);
});

it('validates stop periods', function (): void {
    $this->actingAs(shippingAdmin(), 'admin');

    $this->postJson($this->centralUrl('/admin/shipping/stop-periods'), ['name' => '', 'recurrence' => 'weekly', 'starts_on' => '2026-12-05', 'ends_on' => '2026-12-01', 'is_active' => '1'])
        ->assertJsonValidationErrors(['name', 'ends_on', 'weekday']);
    $this->postJson($this->centralUrl('/admin/shipping/stop-periods'), ['name' => 'x', 'recurrence' => 'daily', 'starts_on' => '5/12/2026', 'is_active' => '1'])
        ->assertJsonValidationErrors(['recurrence', 'starts_on']);
});
