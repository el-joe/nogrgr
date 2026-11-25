<?php

use App\Modules\Commerce\Enums\CouponType;
use App\Modules\Commerce\Enums\PromotionScope;
use App\Modules\Commerce\Events\PromotionsChanged;
use App\Modules\Commerce\Models\Coupon;
use App\Modules\Commerce\Models\CouponRedemption;
use App\Modules\Localization\Models\Country;
use Illuminate\Support\Facades\Event;

require_once __DIR__.'/CommerceHelpers.php';

/*
| ADM-025 / 025a central coupons: permissions, list (country tabs, status, usage),
| create / edit / view modal, validation, active switch, delete, store picker.
*/

const XHR = ['X-Requested-With' => 'XMLHttpRequest'];

function couponPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'nogrgr10',
        'type' => 'percentage',
        'value' => '10%',
        'max_discount' => '$20.00',
        'min_spend' => '$40.00',
        'usage_limit' => '5000',
        'dates_from' => now()->toDateString(),
        'dates_to' => now()->addMonth()->toDateString(),
        'is_active' => '1',
    ], $overrides);
}

it('redirects guests and forbids admins without coupon permissions', function (): void {
    $coupon = Coupon::factory()->create();

    $this->get($this->centralUrl('/admin/promotions/coupons'))->assertRedirect($this->centralUrl('/admin/login'));
    $this->getJson($this->centralUrl('/admin/promotions/coupons/data'))->assertUnauthorized();

    $this->actingAs(commerceAdmin([]), 'admin');
    $this->getJson($this->centralUrl('/admin/promotions/coupons'))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/promotions/coupons/data'))->assertForbidden();
    $this->getJson($this->centralUrl("/admin/promotions/coupons/{$coupon->id}"))->assertForbidden();

    $this->actingAs(commerceAdmin(['coupons.view']), 'admin');
    $this->get($this->centralUrl('/admin/promotions/coupons'))->assertOk()->assertSee(__('commerce::coupons.subtitle'))->assertDontSee(__('commerce::coupons.add'));
    $this->get($this->centralUrl("/admin/promotions/coupons/{$coupon->id}"), XHR)->assertOk()->assertSee('coupon-view', false)->assertDontSee('data-ajax', false);
    $this->getJson($this->centralUrl('/admin/promotions/coupons/create'))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/promotions/coupons/stores'))->assertForbidden();
    $this->postJson($this->centralUrl('/admin/promotions/coupons'), couponPayload())->assertForbidden();
    $this->putJson($this->centralUrl("/admin/promotions/coupons/{$coupon->id}"), couponPayload())->assertForbidden();
    $this->patchJson($this->centralUrl("/admin/promotions/coupons/{$coupon->id}/active"), ['is_active' => false])->assertForbidden();
    $this->deleteJson($this->centralUrl("/admin/promotions/coupons/{$coupon->id}"))->assertForbidden();
});

it('lists coupons with usage across stores, country tabs and status / type filters', function (): void {
    $this->actingAs(commerceAdmin(), 'admin');
    $sa = Country::factory()->iso('SA')->create();
    $eg = Country::factory()->iso('EG')->create();
    $tenant = $this->createTenant('shop');
    $everywhere = Coupon::factory()->create(['code' => 'WELCOME15', 'percentage' => 15, 'max_discount_minor' => 2000, 'min_spend_minor' => 2500]);
    $saudi = Coupon::factory()->fixed(500)->create(['code' => 'SAUDI5', 'usage_limit' => 1]);
    $saudi->countries()->sync([$sa->id]);
    Coupon::factory()->create(['code' => 'EGYPT', 'type' => CouponType::FreeShipping, 'percentage' => null])->countries()->sync([$eg->id]);
    Coupon::factory()->create(['code' => 'LATER', 'starts_at' => now()->addWeek()]);
    CouponRedemption::withoutTenantScope()->create(['coupon_id' => $saudi->id, 'tenant_id' => $tenant->id, 'order_reference' => 'A1', 'redeemed_at' => now()]);

    $this->get($this->centralUrl('/admin/promotions/coupons'))->assertOk()->assertSee($sa->name)->assertSee($eg->name);

    $data = fn (string $query = ''): array => $this->getJson($this->centralUrl('/admin/promotions/coupons/data?draw=1&start=0&length=10'.$query))->assertOk()->json();
    $all = collect($data()['data'])->keyBy(fn ($row) => strip_tags($row['code']) === '' ? '' : trim(explode("\n", trim(strip_tags($row['code'])))[0]));

    expect($data()['recordsTotal'])->toBe(4)
        ->and($all['WELCOME15']['discount'])->toBe('15% off (max $20.00)')
        ->and($all['SAUDI5']['discount'])->toBe('$5.00 off')
        ->and($all['SAUDI5']['usage'])->toContain('1 / 1')
        ->and($all['SAUDI5']['status'])->toContain(__('commerce::enums.coupon_status.used_up'))
        ->and($all['EGYPT']['discount'])->toBe(__('commerce::coupons.labels.free_shipping'))
        ->and($all['WELCOME15']['usage'])->toContain('0 / ∞')
        ->and($all['WELCOME15']['min_spend'])->toContain('$25.00')
        ->and($data('&country_id='.$sa->id)['recordsFiltered'])->toBe(3) // SA-only + the two all-country coupons
        ->and($data('&status=used_up')['recordsFiltered'])->toBe(1)
        ->and($data('&status=scheduled')['recordsFiltered'])->toBe(1)
        ->and($data('&status=active')['recordsFiltered'])->toBe(2)
        ->and($data('&type=fixed')['recordsFiltered'])->toBe(1)
        ->and($data('&search=welc')['recordsFiltered'])->toBe(1)
        ->and($everywhere->exists)->toBeTrue();
});

it('creates, edits, switches and deletes a coupon with store targeting', function (): void {
    Event::fake([PromotionsChanged::class]);
    $this->actingAs(commerceAdmin(['coupons.manage']), 'admin');
    $sa = Country::factory()->iso('SA')->create();
    $tenant = $this->createTenant('target');

    $this->get($this->centralUrl('/admin/promotions/coupons/create'), XHR)->assertOk()->assertSee('coupon-form', false)->assertSee('name="tenant_ids[]"', false);
    $this->getJson($this->centralUrl('/admin/promotions/coupons/stores?q=targ'))->assertOk()->assertJsonPath('results.0.id', $tenant->id);

    $this->postJson($this->centralUrl('/admin/promotions/coupons'), couponPayload([
        'country_ids' => [$sa->id],
        'store_scope' => 'selected',
        'tenant_ids' => [$tenant->id],
        'per_customer_limit' => '1',
        'first_order_only' => '1',
    ]))->assertCreated();

    $coupon = Coupon::query()->sole();
    expect($coupon->code)->toBe('NOGRGR10')
        ->and($coupon->percentage)->toBe(10.0)
        ->and($coupon->max_discount_minor?->minor)->toBe(2000)
        ->and($coupon->min_spend_minor?->minor)->toBe(4000)
        ->and($coupon->usage_limit)->toBe(5000)
        ->and($coupon->first_order_only)->toBeTrue()
        ->and($coupon->store_scope)->toBe(PromotionScope::SelectedStores)
        ->and($coupon->tenants->pluck('id')->all())->toBe([$tenant->id])
        ->and($coupon->countries->pluck('id')->all())->toBe([$sa->id])
        ->and($coupon->ends_at?->toTimeString())->toBe('23:59:59');

    $this->get($this->centralUrl("/admin/promotions/coupons/{$coupon->id}/edit"), XHR)->assertOk()->assertSee('NOGRGR10')->assertSee('target');

    $this->putJson($this->centralUrl("/admin/promotions/coupons/{$coupon->id}"), couponPayload(['code' => 'NOGRGR10', 'type' => 'fixed', 'value' => '$7.50', 'store_scope' => 'all', 'tenant_ids' => [$tenant->id]]))->assertOk();
    $coupon->refresh();
    expect($coupon->type)->toBe(CouponType::Fixed)
        ->and($coupon->amount_minor?->minor)->toBe(750)
        ->and($coupon->percentage)->toBeNull()
        ->and($coupon->max_discount_minor)->toBeNull()
        ->and($coupon->tenants()->count())->toBe(0)
        ->and($coupon->countries()->count())->toBe(0);

    $this->patchJson($this->centralUrl("/admin/promotions/coupons/{$coupon->id}/active"), ['is_active' => false])->assertOk();
    expect($coupon->refresh()->is_active)->toBeFalse();

    $this->deleteJson($this->centralUrl("/admin/promotions/coupons/{$coupon->id}"))->assertOk();
    expect(Coupon::query()->count())->toBe(0)->and(Coupon::withTrashed()->count())->toBe(1);
    Event::assertDispatchedTimes(PromotionsChanged::class, 4);

    // a deleted coupon's code stays reserved (order snapshots keep pointing at it)
    $this->postJson($this->centralUrl('/admin/promotions/coupons'), couponPayload(['code' => 'nogrgr10']))
        ->assertJsonValidationErrors(['code' => __('commerce::coupons.code_taken')]);
});

it('validates the coupon form', function (): void {
    $this->actingAs(commerceAdmin(['coupons.manage']), 'admin');

    $this->postJson($this->centralUrl('/admin/promotions/coupons'), [])
        ->assertJsonValidationErrors(['code', 'type', 'dates_from', 'dates_to']);

    $this->postJson($this->centralUrl('/admin/promotions/coupons'), couponPayload([
        'code' => 'bad code!',
        'value' => '150',
        'usage_limit' => '2',
        'per_customer_limit' => '5',
        'dates_to' => now()->subDay()->toDateString(),
        'store_scope' => 'selected',
        'country_ids' => [999999],
    ]))->assertJsonValidationErrors(['code', 'value', 'per_customer_limit', 'dates_to', 'tenant_ids', 'country_ids.0']);

    $this->postJson($this->centralUrl('/admin/promotions/coupons'), couponPayload(['type' => 'free_shipping', 'value' => '', 'code' => 'FREESHIP']))->assertCreated();
    expect(Coupon::query()->sole()->type)->toBe(CouponType::FreeShipping);
});

it('opens the coupon modal as a full page outside XHR', function (): void {
    $this->actingAs(commerceAdmin(), 'admin');

    $this->get($this->centralUrl('/admin/promotions/coupons/create'))->assertOk()->assertSee('data-success-url', false)->assertSee(__('commerce::coupons.create_title'));
});
