<?php

use App\Modules\Catalog\Enums\TenantChangeStatus;
use App\Modules\Catalog\Enums\TenantChangeType;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Requests\TenantChangeRequest;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Localization\Models\Country;
use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Models\StoreSetting;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Support\Str;

/*
| VEN-071 / 071a / 071b Vendor › Settings › General: permissions, store card validation and
| save (central tenant name + default profit, tenant support e-mail + timezone), countries /
| categories change requests (add / remove, one pending per type, pending strip) and isolation.
*/

beforeEach(function (): void {
    $this->sa = Country::query()->where('iso2', 'SA')->first() ?? Country::factory()->iso('SA')->create();
    $this->ae = Country::query()->where('iso2', 'AE')->first() ?? Country::factory()->iso('AE')->create();
    $this->kw = Country::query()->where('iso2', 'KW')->first() ?? Country::factory()->iso('KW')->create();
    $this->fashion = Category::factory()->create(['name' => ['en' => 'Fashion', 'ar' => 'أزياء']]);
    $this->gaming = Category::factory()->create(['name' => ['en' => 'Gaming', 'ar' => 'ألعاب']]);
    $this->tenant = $this->createTenant('gen-'.Str::lower(Str::random(6)), [
        'name' => 'Megz Store',
        'selling_country_ids' => [$this->sa->id, $this->ae->id],
        'selling_category_ids' => [$this->fashion->id],
        'profit_percent_bps' => 2500,
    ]);
    $this->owner = $this->createVendorOwner($this->tenant, ['email' => 'owner-'.Str::lower(Str::random(6)).'@megz.test']);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/settings/general'.$path);
    $this->setting = fn (StoreSettingKey $key): mixed => $this->tenant->run(fn () => StoreSetting::query()->where('key', $key->value)->first()?->value);
});

function generalPayload(array $overrides = []): array
{
    return array_merge(['store_name' => 'Megz Store KSA', 'support_email' => 'Care@Megz.sa', 'profit_percent' => '62.5', 'timezone' => 'Asia/Riyadh'], $overrides);
}

it('guards every endpoint by login and the settings.general permissions', function (): void {
    $this->get(($this->url)())->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    [$none, $viewer] = $this->tenant->run(function (): array {
        $none = VendorStaff::factory()->create();
        $none->givePermissionTo('dashboard.view');
        $viewer = VendorStaff::factory()->create();
        $viewer->givePermissionTo(['dashboard.view', 'settings.general.view']);

        return [$none, $viewer];
    });

    $this->actingAsVendor($none, $this->tenant);
    $this->get(($this->url)())->assertForbidden();

    $this->actingAsVendor($viewer, $this->tenant);
    $this->get(($this->url)())->assertOk()->assertSee('Megz Store')->assertSee('Saudi Arabia')->assertDontSee(__('platform::vendor_general.request_change'));
    $this->putJson(($this->url)(), generalPayload())->assertForbidden();
    $this->postJson(($this->url)('/requests/countries'), ['add' => [$this->kw->id]])->assertForbidden();
});

it('validates and saves the store card', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->putJson(($this->url)(), ['store_name' => '', 'support_email' => 'nope', 'profit_percent' => 150, 'timezone' => 'Mars/Base'])
        ->assertUnprocessable()->assertJsonValidationErrors(['store_name', 'support_email', 'profit_percent', 'timezone']);

    $this->putJson(($this->url)(), generalPayload())->assertOk();

    $tenant = Tenant::query()->find($this->tenant->id);
    expect($tenant->name)->toBe('Megz Store KSA')
        ->and($tenant->profit_percent_bps)->toBe(6250)
        ->and(($this->setting)(StoreSettingKey::SupportEmail))->toBe('care@megz.sa')
        ->and(($this->setting)(StoreSettingKey::Timezone))->toBe('Asia/Riyadh');

    $this->get(($this->url)())->assertOk()->assertSee('care@megz.sa')->assertSee('62.50')->assertSee('Asia/Riyadh');

    // Clearing the support e-mail removes it.
    $this->putJson(($this->url)(), generalPayload(['support_email' => '']))->assertOk();
    expect(($this->setting)(StoreSettingKey::SupportEmail))->toBeNull();
});

it('sends a countries change request and shows it as pending', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->postJson(($this->url)('/requests/countries'), [])->assertUnprocessable()->assertJsonValidationErrors(['add']);
    // Only countries the store does not sell can be added, only sold ones removed.
    $this->postJson(($this->url)('/requests/countries'), ['add' => [$this->sa->id], 'remove' => [$this->kw->id]])
        ->assertUnprocessable()->assertJsonValidationErrors(['add.0', 'remove.0']);
    $this->postJson(($this->url)('/requests/countries'), ['remove' => [$this->sa->id, $this->ae->id]])
        ->assertUnprocessable()->assertJsonPath('message', __('platform::vendor_general.errors.last_country'));

    $this->postJson(($this->url)('/requests/countries'), ['add' => [$this->kw->id], 'remove' => [$this->ae->id], 'reason' => 'Many Kuwaiti customers order via Instagram.'])->assertOk();

    $request = TenantChangeRequest::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->sole();
    expect($request->type)->toBe(TenantChangeType::Countries)
        ->and($request->status)->toBe(TenantChangeStatus::Pending)
        ->and($request->addedIds())->toBe([$this->kw->id])
        ->and($request->removedIds())->toBe([$this->ae->id])
        ->and($request->vendor_note)->toBe('Many Kuwaiti customers order via Instagram.')
        ->and($request->submitted_by)->toBe($this->owner->name);

    // The store keeps selling the same countries until the admin approves.
    expect(Tenant::query()->find($this->tenant->id)->selling_country_ids)->toBe([$this->sa->id, $this->ae->id]);

    $this->get(($this->url)())->assertOk()->assertSee(__('platform::vendor_general.pending_locked'))->assertSee('Kuwait');
    $this->postJson(($this->url)('/requests/countries'), ['add' => [$this->kw->id]])
        ->assertUnprocessable()->assertJsonPath('message', __('catalog::requests.errors.pending_exists'));
});

it('sends a categories change request independently of countries', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->postJson(($this->url)('/requests/countries'), ['add' => [$this->kw->id]])->assertOk();
    $this->postJson(($this->url)('/requests/categories'), ['add' => [$this->gaming->id], 'remove' => [$this->fashion->id]])->assertOk();
    $this->postJson(($this->url)('/requests/brands'), ['add' => [1]])->assertNotFound();

    $request = TenantChangeRequest::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('type', 'categories')->sole();
    expect($request->requested_ids)->toBe([$this->gaming->id]);
});

it('keeps settings per store and refuses another store panel', function (): void {
    $other = $this->createTenant('gen-o-'.Str::lower(Str::random(6)), ['name' => 'Other Shop', 'profit_percent_bps' => 1000]);
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->putJson(($this->url)(), generalPayload())->assertOk();
    expect(Tenant::query()->find($other->id)->profit_percent_bps)->toBe(1000)
        ->and($other->run(fn () => StoreSetting::query()->where('key', StoreSettingKey::SupportEmail->value)->exists()))->toBeFalse();

    $this->get($this->tenantUrl($other, 'admin/settings/general'))->assertNotFound();
});
