<?php

use App\Modules\Localization\Models\City;
use App\Modules\Localization\Models\Country;
use App\Modules\Shipping\DTOs\QuoteLine;
use App\Modules\Shipping\DTOs\ShippingQuoteRequest;
use App\Modules\Shipping\Enums\ShippingUnavailableReason;
use App\Modules\Shipping\Models\ShippingRule;
use App\Modules\Shipping\Services\ShippingQuoteService;

require_once __DIR__.'/ShippingHelpers.php';

/*
| Shipping rules / zones (ADM-032 / 032a): permissions, list, create / edit with zones and
| bands, band-overlap and country validation, delete, and the DB-backed quote.
*/

function rulePayload(array $countryIds, array $overrides = []): array
{
    return array_replace([
        'name' => 'KSA standard',
        'status' => 'active',
        'country_ids' => $countryIds,
        'zones' => [
            ['name' => 'Riyadh & Central', 'city_ids' => [], 'bands' => [
                ['name' => 'Light', 'min_weight_grams' => '0', 'max_weight_grams' => '1,000', 'price' => '5.00'],
                ['name' => 'Medium', 'min_weight_grams' => '1001', 'max_weight_grams' => '5000', 'price' => '7.50'],
            ]],
        ],
    ], $overrides);
}

it('redirects guests and forbids admins without rule permissions', function (): void {
    $rule = ShippingRule::factory()->create();

    $this->get($this->centralUrl('/admin/shipping/rules'))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(shippingAdmin([]), 'admin');
    $this->getJson($this->centralUrl('/admin/shipping/rules'))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/shipping/rules/data'))->assertForbidden();

    $this->actingAs(shippingAdmin(['shipping.rules.view']), 'admin');
    $this->get($this->centralUrl('/admin/shipping/rules'))->assertOk();
    $this->getJson($this->centralUrl('/admin/shipping/rules/create'))->assertForbidden();
    $this->getJson($this->centralUrl("/admin/shipping/rules/{$rule->id}/edit"))->assertForbidden();
    $this->postJson($this->centralUrl('/admin/shipping/rules'), [])->assertForbidden();
    $this->putJson($this->centralUrl("/admin/shipping/rules/{$rule->id}"), [])->assertForbidden();
    $this->deleteJson($this->centralUrl("/admin/shipping/rules/{$rule->id}"))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/shipping/rules/cities?country=1'))->assertForbidden();
});

it('creates a rule with zones, cities and bands, lists it and quotes from it', function (): void {
    $this->actingAs(shippingAdmin(['shipping.rules.manage']), 'admin');
    $sa = Country::factory()->iso('SA')->create();
    $dammam = City::factory()->create(['country_id' => $sa->id]);

    $this->get($this->centralUrl('/admin/shipping/rules/create'))->assertOk()->assertSee('data-zone-template', false);

    $payload = rulePayload([$sa->id]);
    $payload['zones'][] = ['name' => 'Eastern', 'city_ids' => [$dammam->id], 'bands' => [['name' => 'Light', 'min_weight_grams' => 0, 'max_weight_grams' => 1000, 'price' => '4.50']]];
    $this->postJson($this->centralUrl('/admin/shipping/rules'), $payload)->assertCreated();

    $rule = ShippingRule::query()->with('zones.bands', 'zones.cities')->sole();
    expect($rule->zones)->toHaveCount(2)
        ->and($rule->zones[0]->bands->pluck('price')->map->minor->all())->toBe([500, 750])
        ->and($rule->zones[0]->bands[0]->max_weight_grams)->toBe(1000)
        ->and($rule->zones[1]->cities->pluck('id')->all())->toBe([$dammam->id]);

    $json = $this->getJson($this->centralUrl('/admin/shipping/rules/data?draw=1&start=0&length=10'))->assertOk()->json();
    expect($json['recordsTotal'])->toBe(1)->and($json['data'][0]['bands'])->toContain('3')->and($json['data'][0]['range'])->toContain('0 g – 5 kg');

    $quotes = app(ShippingQuoteService::class);
    expect($quotes->quote(new ShippingQuoteRequest($sa->id, [new QuoteLine('a', 600, 2)]))->amount->toDecimal())->toBe('7.50')
        ->and($quotes->quote(new ShippingQuoteRequest($sa->id, [new QuoteLine('a', 600)], $dammam->id))->amount->toDecimal())->toBe('4.50')
        ->and($quotes->quote(new ShippingQuoteRequest($sa->id, [new QuoteLine('a', 9000)]))->isFree())->toBeTrue()
        ->and($quotes->quote(new ShippingQuoteRequest(Country::factory()->create()->id, [new QuoteLine('a', 600)]))->reason)->toBe(ShippingUnavailableReason::CountryNotServed);

    $rule->update(['status' => 'draft']);
    expect($quotes->quote(new ShippingQuoteRequest($sa->id, [new QuoteLine('a', 600)]))->available)->toBeFalse();
});

it('rejects overlapping bands with the Figma message', function (): void {
    $this->actingAs(shippingAdmin(), 'admin');
    $sa = Country::factory()->iso('SA')->create();

    $payload = rulePayload([$sa->id]);
    $payload['zones'][0]['bands'][1]['min_weight_grams'] = '900';

    $this->postJson($this->centralUrl('/admin/shipping/rules'), $payload)
        ->assertJsonValidationErrors(['zones.0.bands.1.min_weight_grams' => 'Band "Medium" (900 g) overlaps "Light" (0–1,000 g). Bands must not overlap.']);

    $payload = rulePayload([$sa->id]);
    $payload['zones'][0]['bands'][1]['max_weight_grams'] = '10';
    $this->postJson($this->centralUrl('/admin/shipping/rules'), $payload)->assertJsonValidationErrors(['zones.0.bands.1.max_weight_grams']);

    expect(ShippingRule::query()->count())->toBe(0);
});

it('validates rules: required fields, cities of the rule countries, one zone per city', function (): void {
    $this->actingAs(shippingAdmin(), 'admin');
    $sa = Country::factory()->iso('SA')->create();
    $cairo = City::factory()->create(['country_id' => Country::factory()->iso('EG')->create()->id]);
    $riyadh = City::factory()->create(['country_id' => $sa->id]);

    $this->postJson($this->centralUrl('/admin/shipping/rules'), ['name' => '', 'status' => 'x', 'country_ids' => [], 'zones' => []])
        ->assertJsonValidationErrors(['name', 'status', 'country_ids', 'zones']);

    $this->postJson($this->centralUrl('/admin/shipping/rules'), rulePayload([$sa->id], ['zones' => [['name' => 'A', 'city_ids' => [$cairo->id], 'bands' => []]]]))
        ->assertJsonValidationErrors(['zones.0.city_ids.0']);

    $this->postJson($this->centralUrl('/admin/shipping/rules'), rulePayload([$sa->id], ['zones' => [
        ['name' => 'A', 'city_ids' => [$riyadh->id], 'bands' => []],
        ['name' => 'B', 'city_ids' => [$riyadh->id], 'bands' => []],
    ]]))->assertJsonValidationErrors(['zones.1.city_ids']);
});

it('keeps a country in one active rule only, drafts may overlap', function (): void {
    $this->actingAs(shippingAdmin(), 'admin');
    $sa = Country::factory()->iso('SA')->create();
    $existing = ShippingRule::factory()->create(['name' => 'KSA standard']);
    $existing->countries()->attach($sa->id);

    $this->postJson($this->centralUrl('/admin/shipping/rules'), rulePayload([$sa->id], ['name' => 'KSA express']))
        ->assertUnprocessable()->assertJsonPath('message', __('shipping::rules.country_taken', ['country' => $sa->name, 'rule' => 'KSA standard']));

    $this->postJson($this->centralUrl('/admin/shipping/rules'), rulePayload([$sa->id], ['name' => 'KSA draft', 'status' => 'draft']))->assertCreated();
});

it('edits zones in place, removes dropped zones and deletes rules', function (): void {
    $this->actingAs(shippingAdmin(), 'admin');
    $sa = Country::factory()->iso('SA')->create();
    $this->postJson($this->centralUrl('/admin/shipping/rules'), rulePayload([$sa->id]))->assertCreated();
    $rule = ShippingRule::query()->with('zones')->sole();
    $zoneId = $rule->zones[0]->id;

    $this->get($this->centralUrl("/admin/shipping/rules/{$rule->id}/edit"))->assertOk()->assertSee('Riyadh &amp; Central', false);

    $this->putJson($this->centralUrl("/admin/shipping/rules/{$rule->id}"), rulePayload([$sa->id], ['zones' => [
        ['id' => $zoneId, 'name' => 'Central', 'city_ids' => [], 'bands' => [['name' => 'Any', 'min_weight_grams' => 0, 'max_weight_grams' => '', 'price' => '9']]],
    ]]))->assertOk();

    $rule->refresh()->load('zones.bands');
    expect($rule->zones)->toHaveCount(1)->and($rule->zones[0]->id)->toBe($zoneId)->and($rule->zones[0]->name)->toBe('Central')
        ->and($rule->zones[0]->bands[0]->max_weight_grams)->toBeNull();

    $this->deleteJson($this->centralUrl("/admin/shipping/rules/{$rule->id}"))->assertOk();
    expect(ShippingRule::query()->count())->toBe(0);
});

it('suggests cities of the rule countries and filters the list', function (): void {
    $this->actingAs(shippingAdmin(), 'admin');
    $sa = Country::factory()->iso('SA')->create();
    City::factory()->create(['country_id' => $sa->id, 'name' => ['en' => 'Riyadh', 'ar' => 'الرياض'], 'slug' => 'riyadh']);
    ShippingRule::factory()->create(['name' => 'KSA standard'])->countries()->attach($sa->id);
    ShippingRule::factory()->draft()->create(['name' => 'GCC heavy']);

    $this->getJson($this->centralUrl("/admin/shipping/rules/cities?country={$sa->id}&q=riy"))->assertOk()->assertJsonPath('results.0.text', 'Riyadh');
    $this->getJson($this->centralUrl('/admin/shipping/rules/cities?country=abc'))->assertJsonValidationErrors('country');

    expect($this->getJson($this->centralUrl("/admin/shipping/rules/data?draw=1&start=0&length=10&country_id={$sa->id}"))->json('recordsFiltered'))->toBe(1)
        ->and($this->getJson($this->centralUrl('/admin/shipping/rules/data?draw=1&start=0&length=10&status=draft'))->json('recordsFiltered'))->toBe(1)
        ->and($this->getJson($this->centralUrl('/admin/shipping/rules/data?draw=1&start=0&length=10&search=gcc'))->json('recordsFiltered'))->toBe(1);
});
