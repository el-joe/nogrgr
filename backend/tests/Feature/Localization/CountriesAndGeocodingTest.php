<?php

use App\Modules\Localization\Database\Seeders\CitySeeder;
use App\Modules\Localization\Database\Seeders\CountrySeeder;
use App\Modules\Localization\Enums\CityType;
use App\Modules\Localization\Enums\Continent;
use App\Modules\Localization\Models\City;
use App\Modules\Localization\Models\Country;
use App\Modules\Localization\ModuleServiceProvider;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

/*
| Central countries / cities seed data and the storefront address helpers:
| /geo/countries, /geo/countries/{id}/cities, /geo/reverse, /geo/ip.
*/

it('seeds every ISO 3166-1 country with names, codes, currency and flag', function (): void {
    $this->seed(CountrySeeder::class);

    $saudi = Country::query()->where('iso2', 'SA')->sole();

    expect(Country::query()->count())->toBe(249)
        ->and($saudi->iso3)->toBe('SAU')
        ->and($saudi->getTranslation('name', 'en'))->toBe('Saudi Arabia')
        ->and($saudi->getTranslation('name', 'ar'))->toBe('المملكة العربية السعودية')
        ->and($saudi->phone_code)->toBe('966')
        ->and($saudi->currency_code)->toBe('SAR')
        ->and($saudi->flag)->toBe('🇸🇦')
        ->and($saudi->continent)->toBe(Continent::Asia)
        ->and(Country::query()->where('iso2', 'EG')->value('currency_code'))->toBe('EGP')
        ->and(Country::query()->where('iso2', 'AQ')->value('is_active'))->toBeFalse()
        ->and(Country::query()->whereNull('iso3')->count())->toBe(0);
});

it('seeds major cities idempotently and keeps admin activation choices', function (): void {
    $this->seed(CitySeeder::class);
    $count = City::query()->count();
    $riyadh = City::query()->where('slug', 'riyadh')->sole();

    Country::query()->where('iso2', 'SA')->update(['is_active' => false]);
    $this->seed([CountrySeeder::class, CitySeeder::class]);

    expect($count)->toBeGreaterThan(400)
        ->and(City::query()->count())->toBe($count)
        ->and($riyadh->type)->toBe(CityType::Capital)
        ->and($riyadh->getTranslation('name', 'ar'))->toBe('الرياض')
        ->and(Country::query()->where('iso2', 'SA')->value('is_active'))->toBeFalse();

    foreach (['SA', 'AE', 'KW', 'QA', 'BH', 'OM', 'EG', 'JO', 'LB', 'SY', 'PS', 'IQ', 'MA', 'DZ', 'TN', 'LY'] as $iso2) {
        expect(City::query()->whereRelation('country', 'iso2', $iso2)->count())->toBeGreaterThan(5, $iso2);
    }
});

it('lists the countries the store sells to, sorted by localized name', function (): void {
    $saudi = Country::factory()->iso('SA')->create();
    $egypt = Country::factory()->iso('EG')->create();
    Country::factory()->iso('KW')->inactive()->create();
    $tenant = $this->createTenant('acme');

    $all = $this->getJson($this->tenantUrl($tenant, '/geo/countries'))->assertOk()->assertJson(['success' => true]);
    expect(collect($all->json('data'))->pluck('iso2')->all())->toBe(['EG', 'SA'])
        ->and($all->json('data.0'))->toHaveKeys(['id', 'iso2', 'name', 'flag', 'phone_code', 'currency_code']);

    $this->endTenancy();
    $tenant->update(['selling_country_ids' => [$saudi->id]]);
    app()->forgetScopedInstances();

    expect(collect($this->getJson($this->tenantUrl($tenant, '/geo/countries'))->json('data'))->pluck('id')->all())->toBe([$saudi->id]);
    $this->getJson($this->tenantUrl($tenant, '/geo/countries/'.$egypt->id.'/cities'))->assertNotFound();
});

it('suggests cities of a country in both languages, capitals first', function (): void {
    $saudi = Country::factory()->iso('SA')->create();
    City::factory()->for($saudi)->named(['en' => 'Jeddah', 'ar' => 'جدة'])->create();
    City::factory()->for($saudi)->named(['en' => 'Riyadh', 'ar' => 'الرياض'])->create(['type' => CityType::Capital]);
    $tenant = $this->createTenant('acme');

    $this->getJson($this->tenantUrl($tenant, '/geo/countries/'.$saudi->id.'/cities'))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Riyadh')
        ->assertJsonPath('data.0.type', 'capital')
        ->assertJsonCount(2, 'data');

    $this->getJson($this->tenantUrl($tenant, '/geo/countries/'.$saudi->id.'/cities?q=جد'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Jeddah');
});

it('reverse geocodes server-side with a generic user agent and caches the result', function (): void {
    $saudi = Country::factory()->iso('SA')->create();
    $tenant = $this->createTenant('acme');
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response(['address' => [
        'house_number' => '12', 'road' => 'King Fahd Road', 'suburb' => 'Al Olaya',
        'city' => 'Riyadh', 'state' => 'Riyadh Province', 'country_code' => 'sa',
    ]])]);

    foreach ([1, 2] as $call) {
        $this->getJson($this->tenantUrl($tenant, '/geo/reverse?lat=24.711&lng=46.674'))
            ->assertOk()
            ->assertJson(['success' => true, 'data' => [
                'address_line_1' => '12 King Fahd Road, Al Olaya',
                'city' => 'Riyadh',
                'state' => 'Riyadh Province',
                'country_iso2' => 'SA',
                'country_id' => $saudi->id,
                'approximate' => false,
            ]]);
    }

    Http::assertSentCount(1);
    Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/reverse')
        && $request['lat'] == 24.711
        && $request->header('User-Agent')[0] === config('services.geocoding.user_agent')
        && ! str_contains($request->header('User-Agent')[0], '@'));
});

it('validates coordinates and reports a failed lookup', function (): void {
    $tenant = $this->createTenant('acme');
    Http::fake(['*' => Http::response([], 500)]);

    $this->getJson($this->tenantUrl($tenant, '/geo/reverse?lat=95&lng=10'))->assertUnprocessable()->assertJsonValidationErrors('lat');
    $this->getJson($this->tenantUrl($tenant, '/geo/reverse?lng=10'))->assertUnprocessable()->assertJsonValidationErrors('lat');
    $this->getJson($this->tenantUrl($tenant, '/geo/reverse?lat=10&lng=200'))->assertUnprocessable()->assertJsonValidationErrors('lng');

    $this->getJson($this->tenantUrl($tenant, '/geo/reverse?lat=10&lng=10'))
        ->assertNotFound()
        ->assertJson(['success' => false, 'message' => __('localization::geo.not_found')]);
});

it('locates the visitor IP and falls back to the visitor country for private addresses', function (): void {
    $emirates = Country::factory()->iso('AE')->create();
    $saudi = Country::factory()->iso('SA')->create();
    $tenant = $this->createTenant('acme');
    Http::fake(['ipwho.is/*' => Http::response(['success' => true, 'city' => 'Dubai', 'region' => 'Dubai', 'country_code' => 'AE', 'latitude' => 25.2, 'longitude' => 55.27])]);

    $this->withServerVariables(['REMOTE_ADDR' => '94.200.1.1'])
        ->getJson($this->tenantUrl($tenant, '/geo/ip'))
        ->assertOk()
        ->assertJson(['message' => __('localization::geo.approximate'), 'data' => ['city' => 'Dubai', 'country_id' => $emirates->id, 'approximate' => true]]);

    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->getJson($this->tenantUrl($tenant, '/geo/ip'))
        ->assertOk()
        ->assertJson(['data' => ['city' => null, 'country_iso2' => 'SA', 'country_id' => $saudi->id, 'approximate' => true]]);

    Http::assertSentCount(1);
});

it('rate limits the geocoding endpoints per visitor', function (): void {
    $tenant = $this->createTenant('acme');
    Http::fake(['*' => Http::response(['success' => false])]);

    foreach (range(1, ModuleServiceProvider::GEOCODING_PER_MINUTE) as $attempt) {
        $this->getJson($this->tenantUrl($tenant, '/geo/ip'))->assertOk();
    }

    $this->getJson($this->tenantUrl($tenant, '/geo/ip'))->assertTooManyRequests();
});
