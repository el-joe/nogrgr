<?php

use App\Modules\Localization\Models\Country;
use App\Modules\Shipping\DTOs\ShippingSettingsData;
use App\Modules\Shipping\Models\ShippingCompany;
use App\Modules\Shipping\Models\ShippingDayRule;
use App\Modules\Shipping\Models\ShippingStopPeriod;
use App\Modules\Shipping\Services\DeliveryEstimateService;
use App\Modules\Shipping\Services\ShippingSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/ShippingHelpers.php';

/*
| Shipping companies (ADM-033 / 033a–d): permissions, list, create / edit with logo and
| tracking pattern, active toggle, day rules with the default company, estimator.
*/

it('redirects guests and forbids admins without company permissions', function (): void {
    $company = ShippingCompany::factory()->create();

    $this->get($this->centralUrl('/admin/shipping/companies'))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(shippingAdmin([]), 'admin');
    $this->getJson($this->centralUrl('/admin/shipping/companies'))->assertForbidden();
    $this->getJson($this->centralUrl("/admin/shipping/companies/{$company->id}"))->assertForbidden();

    $this->actingAs(shippingAdmin(['shipping.companies.view']), 'admin');
    $this->get($this->centralUrl('/admin/shipping/companies'))->assertOk();
    $this->get($this->centralUrl("/admin/shipping/companies/{$company->id}"))->assertOk()->assertSee($company->name)->assertDontSee('day-rules/create', false);
    $this->getJson($this->centralUrl("/admin/shipping/companies/{$company->id}/edit"))->assertForbidden();
    $this->postJson($this->centralUrl('/admin/shipping/companies'), [])->assertForbidden();
    $this->patchJson($this->centralUrl("/admin/shipping/companies/{$company->id}/active"), ['is_active' => 0])->assertForbidden();
    $this->deleteJson($this->centralUrl("/admin/shipping/companies/{$company->id}"))->assertForbidden();
    $this->postJson($this->centralUrl("/admin/shipping/companies/{$company->id}/day-rules"), [])->assertForbidden();
});

it('creates a company with a logo and tracking pattern, edits, toggles and deletes it', function (): void {
    Storage::fake('public');
    $this->actingAs(shippingAdmin(['shipping.companies.manage']), 'admin');

    $this->get($this->centralUrl('/admin/shipping/companies/create'))->assertOk();
    $this->post($this->centralUrl('/admin/shipping/companies'), [
        'name' => 'Aramex',
        'contact_email' => 'support@aramex.test',
        'contact_phone' => '0501234567',
        'contact_phone_country' => 'sa',
        'website' => 'https://www.aramex.com',
        'tracking_url_pattern' => 'https://www.aramex.com/track/{tracking}',
        'is_active' => '1',
        'logo' => [UploadedFile::fake()->image('logo.png', 200, 200)],
    ], ['Accept' => 'application/json'])->assertCreated();

    $company = ShippingCompany::query()->sole();
    expect($company->contact_phone)->toBe('+966501234567')
        ->and($company->trackingUrl('AB 12'))->toBe('https://www.aramex.com/track/AB%2012');
    Storage::disk('public')->assertExists($company->logo_path);

    $this->get($this->centralUrl("/admin/shipping/companies/{$company->id}/edit"))->assertOk()->assertSee('Aramex');

    $old = $company->logo_path;
    $this->putJson($this->centralUrl("/admin/shipping/companies/{$company->id}"), ['name' => 'Aramex KSA', 'is_active' => '1', 'tracking_url_pattern' => ''])->assertOk();
    expect($company->refresh()->name)->toBe('Aramex KSA')->and($company->logo_path)->toBeNull()->and($company->tracking_url_pattern)->toBeNull();
    Storage::disk('public')->assertMissing($old);

    $this->patchJson($this->centralUrl("/admin/shipping/companies/{$company->id}/active"), ['is_active' => 0])->assertOk();
    expect($company->refresh()->is_active)->toBeFalse();

    $json = $this->getJson($this->centralUrl('/admin/shipping/companies/data?draw=1&start=0&length=10&status=inactive'))->assertOk()->json();
    expect($json['recordsFiltered'])->toBe(1);

    $this->deleteJson($this->centralUrl("/admin/shipping/companies/{$company->id}"))->assertOk();
    expect(ShippingCompany::query()->count())->toBe(0);
});

it('validates companies', function (): void {
    $this->actingAs(shippingAdmin(), 'admin');

    $this->postJson($this->centralUrl('/admin/shipping/companies'), [
        'name' => '',
        'contact_email' => 'nope',
        'website' => 'ftp://x',
        'tracking_url_pattern' => 'https://track.test/no-placeholder',
        'is_active' => '1',
    ])->assertJsonValidationErrors(['name', 'contact_email', 'website', 'tracking_url_pattern' => __('shipping::companies.tracking_placeholder_missing')]);
});

it('manages day rules with one row per country and one default company per country', function (): void {
    $this->actingAs(shippingAdmin(), 'admin');
    $sa = Country::factory()->iso('SA')->create();
    $aramex = ShippingCompany::factory()->create(['name' => 'Aramex']);
    $dhl = ShippingCompany::factory()->create(['name' => 'DHL']);

    $this->getJson($this->centralUrl("/admin/shipping/companies/{$aramex->id}/day-rules/create"), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertSee('min_days', false);
    $this->postJson($this->centralUrl("/admin/shipping/companies/{$aramex->id}/day-rules"), ['country_id' => $sa->id, 'min_days' => 1, 'max_days' => 3, 'is_default' => 1])->assertCreated();
    $this->postJson($this->centralUrl("/admin/shipping/companies/{$aramex->id}/day-rules"), ['country_id' => $sa->id, 'min_days' => 1, 'max_days' => 3])
        ->assertJsonValidationErrors(['country_id' => __('shipping::companies.day_rules.duplicate')]);
    $this->postJson($this->centralUrl("/admin/shipping/companies/{$dhl->id}/day-rules"), ['country_id' => $sa->id, 'min_days' => 4, 'max_days' => 2])->assertJsonValidationErrors(['max_days']);

    $this->postJson($this->centralUrl("/admin/shipping/companies/{$dhl->id}/day-rules"), ['country_id' => $sa->id, 'min_days' => 2, 'max_days' => 4, 'is_default' => 1])->assertCreated();
    expect(app(ShippingSettingsService::class)->defaultsTable())->toBe([['country_id' => $sa->id, 'company_id' => $dhl->id, 'company' => 'DHL', 'min_days' => 2, 'max_days' => 4]]);

    $rule = ShippingDayRule::query()->where('shipping_company_id', $dhl->id)->sole();
    $this->get($this->centralUrl("/admin/shipping/companies/{$dhl->id}/edit?tab=day-rules"))->assertOk()->assertSee(__('shipping::companies.day_rules.default_badge'));
    $this->getJson($this->centralUrl("/admin/shipping/companies/{$aramex->id}/day-rules/{$rule->id}/edit"))->assertNotFound();
    $this->putJson($this->centralUrl("/admin/shipping/companies/{$dhl->id}/day-rules/{$rule->id}"), ['country_id' => $sa->id, 'min_days' => 2, 'max_days' => 5])->assertOk();
    expect($rule->refresh()->max_days)->toBe(5)->and(app(ShippingSettingsService::class)->defaultsTable())->toBe([]);

    $this->deleteJson($this->centralUrl("/admin/shipping/companies/{$dhl->id}/day-rules/{$rule->id}"))->assertOk();
    expect(ShippingDayRule::query()->count())->toBe(1);
});

it('previews the delivery estimate skipping off days and stop periods', function (): void {
    $this->actingAs(shippingAdmin(['shipping.companies.view']), 'admin');
    $sa = Country::factory()->iso('SA')->create();
    $aramex = ShippingCompany::factory()->create();
    ShippingDayRule::query()->create(['shipping_company_id' => $aramex->id, 'country_id' => $sa->id, 'min_days' => 1, 'max_days' => 3]);
    app(ShippingSettingsService::class)->update(new ShippingSettingsData(weeklyOffDays: [CarbonImmutable::FRIDAY]));
    ShippingStopPeriod::query()->create(['name' => 'Hub maintenance', 'shipping_company_id' => $aramex->id, 'recurrence' => 'once', 'starts_on' => '2026-12-26', 'is_active' => true]);
    ShippingStopPeriod::query()->create(['name' => 'Other carrier', 'shipping_company_id' => ShippingCompany::factory()->create()->id, 'recurrence' => 'once', 'starts_on' => '2026-12-27', 'is_active' => true]);

    $this->getJson($this->centralUrl("/admin/shipping/companies/estimate?country_id={$sa->id}&date=2026-12-24&company_id={$aramex->id}"))
        ->assertOk()
        ->assertJsonPath('min_date', '2026-12-27')
        ->assertJsonPath('max_date', '2026-12-29')
        ->assertJsonPath('skipped.1.reason', 'Hub maintenance');

    $this->getJson($this->centralUrl('/admin/shipping/companies/estimate?date=bad'))->assertJsonValidationErrors(['country_id', 'date']);
});

it('falls back to the default company, then to the settings days', function (): void {
    $sa = Country::factory()->iso('SA')->create();
    $eg = Country::factory()->iso('EG')->create();
    $aramex = ShippingCompany::factory()->create();
    ShippingDayRule::query()->create(['shipping_company_id' => $aramex->id, 'country_id' => $sa->id, 'min_days' => 1, 'max_days' => 2]);
    app(ShippingSettingsService::class)->setDefaultCompany($sa->id, $aramex->id);

    $estimates = app(DeliveryEstimateService::class);
    $from = CarbonImmutable::parse('2026-11-24 09:00');

    expect($estimates->estimateFor($sa->id, null, $from)->maxDate->toDateString())->toBe('2026-11-26')
        ->and($estimates->estimateFor($sa->id, null, $from)->companyId)->toBe($aramex->id)
        ->and($estimates->estimateFor($eg->id, null, $from)->toArray())->toMatchArray(['min_days' => 2, 'max_days' => 5, 'max_date' => '2026-11-29']);
});
