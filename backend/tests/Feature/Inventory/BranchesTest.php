<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;
use App\Modules\Inventory\Events\StockChanged;
use App\Modules\Inventory\Models\Branch;
use App\Modules\Inventory\Models\BranchStock;
use App\Modules\Localization\Models\City;
use App\Modules\Localization\Models\Country;
use App\Modules\Shipping\Models\ShippingRule;
use App\Modules\Shipping\Models\ShippingZone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;

/*
| Branches (ADM-030 / 030a): permissions, list + filters, create / edit with default
| countries (one branch per country) and zones, stock table, CSV import, delete guard.
*/

function inventoryAdmin(?array $permissions = null): Admin
{
    test()->seed(CentralPermissionSeeder::class);
    $admin = Admin::factory()->create();

    $permissions === null
        ? $admin->assignRole('super-admin')
        : $admin->givePermissionTo(['dashboard.view', ...$permissions]);

    return $admin;
}

function branchPayload(Country $country, array $overrides = []): array
{
    return array_replace([
        'name' => ['en' => 'Riyadh WH', 'ar' => 'مستودع الرياض'],
        'code' => 'ruh-01',
        'country_id' => $country->id,
        'city_id' => '',
        'address' => 'Al Sulay industrial area, Gate 4',
        'phone' => '0501234567',
        'phone_country' => 'sa',
        'pickup_hours' => 'Sun–Thu 08:00–20:00',
        'allows_pickup' => '0',
        'is_active' => '1',
        'default_country_ids' => [$country->id],
        'zone_ids' => [],
    ], $overrides);
}

it('redirects guests and forbids admins without branch permissions', function (): void {
    $branch = Branch::factory()->create();

    $this->get($this->centralUrl('/admin/shipping/branches'))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(inventoryAdmin([]), 'admin');
    $this->getJson($this->centralUrl('/admin/shipping/branches'))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/shipping/branches/data'))->assertForbidden();

    $this->actingAs(inventoryAdmin(['shipping.branches.view']), 'admin');
    $this->get($this->centralUrl('/admin/shipping/branches'))->assertOk();
    $this->getJson($this->centralUrl("/admin/shipping/branches/{$branch->id}/stock"))->assertOk();
    $this->getJson($this->centralUrl('/admin/shipping/branches/create'))->assertForbidden();
    $this->postJson($this->centralUrl('/admin/shipping/branches'), [])->assertForbidden();
    $this->putJson($this->centralUrl("/admin/shipping/branches/{$branch->id}"), [])->assertForbidden();
    $this->deleteJson($this->centralUrl("/admin/shipping/branches/{$branch->id}"))->assertForbidden();
    $this->postJson($this->centralUrl("/admin/shipping/branches/{$branch->id}/stock/import"), [])->assertForbidden();
});

it('serves the branches table with stock totals, default countries and filters', function (): void {
    $this->actingAs(inventoryAdmin(), 'admin');
    $sa = Country::factory()->iso('SA')->create();
    $eg = Country::factory()->iso('EG')->create();
    $riyadh = Branch::factory()->create(['country_id' => $sa->id, 'name' => ['en' => 'Riyadh WH', 'ar' => 'الرياض']]);
    Branch::factory()->inactive()->create(['country_id' => $eg->id]);
    $riyadh->defaultCountries()->attach($sa->id);
    $product = Product::factory()->create();
    BranchStock::query()->create(['branch_id' => $riyadh->id, 'product_id' => $product->id, 'variant_key' => 0, 'on_hand' => 120]);

    $json = $this->getJson($this->centralUrl('/admin/shipping/branches/data?draw=1&start=0&length=10'))->assertOk()->json();
    expect($json['recordsTotal'])->toBe(2);
    $row = collect($json['data'])->firstWhere('id', $riyadh->id);
    expect($row['units_sum'])->toContain('120')->and($row['skus_count'])->toContain('1')->and($row['default_for'])->toBe('SA');

    expect($this->getJson($this->centralUrl("/admin/shipping/branches/data?draw=1&start=0&length=10&country_id={$eg->id}"))->json('recordsFiltered'))->toBe(1)
        ->and($this->getJson($this->centralUrl('/admin/shipping/branches/data?draw=1&start=0&length=10&status=active'))->json('recordsFiltered'))->toBe(1)
        ->and($this->getJson($this->centralUrl('/admin/shipping/branches/data?draw=1&start=0&length=10&search[value]=riyadh'))->json('recordsFiltered'))->toBe(1);
});

it('creates a branch with an E.164 phone, default countries and zones', function (): void {
    $this->actingAs(inventoryAdmin(['shipping.branches.manage']), 'admin');
    $sa = Country::factory()->iso('SA')->create();
    $city = City::factory()->create(['country_id' => $sa->id]);
    $rule = ShippingRule::factory()->create();
    $rule->countries()->attach($sa->id);
    $zone = ShippingZone::query()->create(['shipping_rule_id' => $rule->id, 'name' => 'Riyadh & Central']);

    $this->get($this->centralUrl('/admin/shipping/branches/create'))->assertOk()->assertSee('name[ar]', false);

    $this->postJson($this->centralUrl('/admin/shipping/branches'), branchPayload($sa, ['city_id' => $city->id, 'zone_ids' => [$zone->id]]))->assertCreated();

    $branch = Branch::query()->sole();
    expect($branch->code)->toBe('RUH-01')
        ->and($branch->phone)->toBe('+966501234567')
        ->and($branch->getTranslation('name', 'ar'))->toBe('مستودع الرياض')
        ->and($branch->defaultCountries->pluck('id')->all())->toBe([$sa->id])
        ->and($branch->zones->pluck('id')->all())->toBe([$zone->id]);

    $this->get($this->centralUrl("/admin/shipping/branches/{$branch->id}/edit"))->assertOk()->assertSee('RUH-01');
});

it('moves the default of a country to the newest branch that claims it', function (): void {
    $this->actingAs(inventoryAdmin(), 'admin');
    $sa = Country::factory()->iso('SA')->create();
    $first = Branch::factory()->create(['country_id' => $sa->id]);
    $first->defaultCountries()->attach($sa->id);

    $this->postJson($this->centralUrl('/admin/shipping/branches'), branchPayload($sa, ['code' => 'JED-01']))->assertCreated();

    expect($first->defaultCountries()->count())->toBe(0)
        ->and(Branch::query()->where('code', 'JED-01')->sole()->defaultCountries()->pluck('countries.id')->all())->toBe([$sa->id]);
});

it('validates branches', function (): void {
    $this->actingAs(inventoryAdmin(), 'admin');
    $sa = Country::factory()->iso('SA')->create();
    $eg = Country::factory()->iso('EG')->create();
    Branch::factory()->create(['code' => 'RUH-01', 'country_id' => $sa->id]);
    $cairo = City::factory()->create(['country_id' => $eg->id]);
    $egRule = ShippingRule::factory()->create();
    $egRule->countries()->attach($eg->id);
    $egZone = ShippingZone::query()->create(['shipping_rule_id' => $egRule->id, 'name' => 'Cairo']);

    $this->postJson($this->centralUrl('/admin/shipping/branches'), branchPayload($sa, [
        'name' => ['en' => ''],
        'phone' => '12',
        'city_id' => $cairo->id,
        'zone_ids' => [$egZone->id],
    ]))->assertJsonValidationErrors(['name.en', 'name.ar', 'code', 'phone', 'city_id', 'zone_ids.0']);
});

it('lists the branch stock and imports a CSV by SKU', function (): void {
    Event::fake([StockChanged::class]);
    $this->actingAs(inventoryAdmin(), 'admin');
    $branch = Branch::factory()->create();
    $simple = Product::factory()->create(['sku' => 'NG-1']);
    $variant = ProductVariant::factory()->create(['sku' => 'NG-2-BK']);

    $csv = UploadedFile::fake()->createWithContent('stock.csv', "sku,on_hand\nNG-1,40\nNG-2-BK,7\nMISSING,3\n");
    $this->post($this->centralUrl("/admin/shipping/branches/{$branch->id}/stock/import"), ['file' => $csv], ['Accept' => 'application/json'])
        ->assertOk()->assertJson(['data' => ['updated' => 2, 'unknown' => ['MISSING']]]);

    expect($simple->refresh()->stock)->toBe(40)
        ->and($variant->refresh()->stock)->toBe(7)
        ->and($variant->product->refresh()->stock)->toBe(7);
    Event::assertDispatched(StockChanged::class, fn (StockChanged $event): bool => $event->productId === $simple->id && $event->totalStock === 40);

    $json = $this->getJson($this->centralUrl("/admin/shipping/branches/{$branch->id}/stock?draw=1&start=0&length=10"))->assertOk()->json();
    expect($json['recordsTotal'])->toBe(2);

    $bad = UploadedFile::fake()->createWithContent('bad.csv', "code,qty\nX,1\n");
    $this->post($this->centralUrl("/admin/shipping/branches/{$branch->id}/stock/import"), ['file' => $bad], ['Accept' => 'application/json'])
        ->assertJsonValidationErrors('file');
});

it('deletes an empty branch but not one holding stock', function (): void {
    $this->actingAs(inventoryAdmin(), 'admin');
    $full = Branch::factory()->create();
    $empty = Branch::factory()->create();
    BranchStock::query()->create(['branch_id' => $full->id, 'product_id' => Product::factory()->create()->id, 'variant_key' => 0, 'on_hand' => 5]);

    $this->deleteJson($this->centralUrl("/admin/shipping/branches/{$full->id}"))->assertUnprocessable();
    $this->deleteJson($this->centralUrl("/admin/shipping/branches/{$empty->id}"))->assertOk();

    expect($full->refresh()->trashed())->toBeFalse()->and($empty->refresh()->trashed())->toBeTrue();
});

it('suggests the cities of a country for the city and zone pickers', function (): void {
    $this->actingAs(inventoryAdmin(), 'admin');
    $sa = Country::factory()->iso('SA')->create();
    City::factory()->create(['country_id' => $sa->id, 'name' => ['en' => 'Riyadh', 'ar' => 'الرياض'], 'slug' => 'riyadh']);

    $this->getJson($this->centralUrl("/admin/shipping/branches/cities?country={$sa->id}&q=riy"))
        ->assertOk()->assertJsonPath('results.0.text', 'Riyadh');
    $this->getJson($this->centralUrl('/admin/shipping/branches/cities'))->assertJsonValidationErrors('country');
});

it('suggests the shipping zones of the rules covering a country for the zone picker', function (): void {
    $this->actingAs(inventoryAdmin(), 'admin');
    $sa = Country::factory()->iso('SA')->create();
    $eg = Country::factory()->iso('EG')->create();
    $rule = ShippingRule::factory()->create(['name' => 'KSA standard']);
    $rule->countries()->attach($sa->id);
    ShippingZone::query()->create(['shipping_rule_id' => $rule->id, 'name' => 'Eastern']);
    $other = ShippingRule::factory()->create();
    $other->countries()->attach($eg->id);
    ShippingZone::query()->create(['shipping_rule_id' => $other->id, 'name' => 'Cairo']);

    $this->getJson($this->centralUrl("/admin/shipping/branches/zones?country={$sa->id}"))
        ->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('results.0.text', 'KSA standard › Eastern');
});
