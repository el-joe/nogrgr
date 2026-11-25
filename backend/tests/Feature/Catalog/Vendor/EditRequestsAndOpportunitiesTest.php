<?php

use App\Modules\Catalog\Enums\EditRequestStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Requests\EditRequest;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Models\StoreProductMarket;
use App\Modules\Catalog\Services\Requests\EditRequestService;
use App\Modules\Identity\Models\Admin;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/VendorCatalogHelpers.php';

/*
| VEN-013 / 013a edit requests (submit through EditRequestService, history scoped to the
| store, approval → tenant override shown by the reader) and VEN-015 / 015a / 015b
| today's chances with the select markets modal.
*/

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('public');
    Notification::fake();
    [$this->tenant, $this->owner, $this->sa, $this->ae] = catalogStore();
    $this->central = Product::factory()->create(['name' => ['en' => 'Wireless Earbuds Pro', 'ar' => 'سماعات لاسلكية برو'], 'weight_grams' => 180]);
    $this->product = storeProduct($this->tenant, $this->central->id);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/catalog'.$path);
});

it('renders the request modal with the current values and submits a field diff', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)("/edit-requests/form?product={$this->product->id}"))
        ->assertOk()
        ->assertSee('edit-request-form', false)
        ->assertSee('سماعات لاسلكية برو');

    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('/edit-requests'), ['product_id' => $this->product->id, 'field_key' => 'name:ar'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('value');

    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('/edit-requests'), [
        'product_id' => $this->product->id,
        'field_key' => 'name:ar',
        'value' => 'سماعات ميغز برو',
        'note' => 'Customers search using our store name.',
    ])->assertOk();

    $request = EditRequest::withoutTenantScope()->sole();
    expect($request->tenant_id)->toBe($this->tenant->id)
        ->and($request->product_id)->toBe($this->central->id)
        ->and($request->changes)->toBe([['field' => 'name', 'locale' => 'ar', 'current' => 'سماعات لاسلكية برو', 'requested' => 'سماعات ميغز برو']]);

    // One pending request per product.
    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('/edit-requests'), ['product_id' => $this->product->id, 'field_key' => 'weight_grams', 'value' => 250])->assertUnprocessable();
});

it('requests images and weight changes', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant)->post(($this->url)('/edit-requests'), [
        'product_id' => $this->product->id,
        'field_key' => 'images',
        'images' => [UploadedFile::fake()->image('front.jpg')],
    ], ['Accept' => 'application/json'])->assertOk();

    $request = EditRequest::withoutTenantScope()->sole();
    expect($request->changes[0]['field'])->toBe('images')->and($request->files)->toHaveCount(1);

    $request->update(['status' => EditRequestStatus::Rejected, 'reason' => 'Low resolution']);
    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('/edit-requests'), ['product_id' => $this->product->id, 'field_key' => 'weight_grams', 'value' => 250])->assertOk();

    expect(EditRequest::withoutTenantScope()->latest('id')->first()->changes[0])->toMatchArray(['field' => 'weight_grams', 'current' => 180, 'requested' => 250]);
});

it('lists only the store\'s own requests and shows an approved change on the product', function (): void {
    [$beta] = catalogStore('beta');
    EditRequest::factory()->create(['tenant_id' => $beta->id, 'product_id' => $this->central->id]);
    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('/edit-requests'), ['product_id' => $this->product->id, 'field_key' => 'name:en', 'value' => 'Megz Earbuds'])->assertOk();

    $json = $this->actingAsVendor($this->owner, $this->tenant)->getJson(($this->url)('/edit-requests/data?draw=1&start=0&length=10'))->assertOk()->json();
    expect($json['recordsFiltered'])->toBe(1);

    $own = EditRequest::withoutTenantScope()->where('tenant_id', $this->tenant->id)->sole();
    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)("/edit-requests/{$own->id}"))->assertOk()->assertSee('Megz Earbuds');
    $foreign = EditRequest::withoutTenantScope()->where('tenant_id', $beta->id)->sole();
    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)("/edit-requests/{$foreign->id}"))->assertNotFound();

    app(EditRequestService::class)->approve($own, Admin::factory()->create());

    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)("/products/{$this->product->id}/edit"))->assertOk()->assertSee('Megz Earbuds');
    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)('/edit-requests'))->assertOk()->assertSee(__('catalog::vendor.edit_requests.new'));
});

it('lists today\'s chances with the already-added state', function (): void {
    $fresh = Product::factory()->create(['name' => ['en' => 'Trending Lamp'], 'sold_count' => 40]);

    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)('/opportunities'))->assertOk();
    $cards = collect($this->actingAsVendor($this->owner, $this->tenant)->getJson(($this->url)('/opportunities/data?draw=1&start=0&length=12'))->assertOk()->json('data'))->pluck('card');

    expect($cards[0])->toContain('Trending Lamp')->not->toContain(__('catalog::vendor.opportunities.already'))
        ->and($cards[1])->toContain(__('catalog::vendor.opportunities.already'));

    expect($this->actingAsVendor($this->owner, $this->tenant)->getJson(($this->url)('/opportunities/data?draw=1&start=0&length=12&added=no'))->json('recordsFiltered'))->toBe(1);
});

it('adds an opportunity to chosen markets and shows them as already added', function (): void {
    $lamp = Product::factory()->create(['name' => ['en' => 'Trending Lamp']]);
    $markets = ($this->url)("/opportunities/{$lamp->id}/markets");

    $this->actingAsVendor($this->owner, $this->tenant)->get($markets)->assertOk()->assertSee('data-markets-form', false)->assertSee($this->sa->name);
    $this->actingAsVendor($this->owner, $this->tenant)->postJson($markets, ['countries' => []])->assertUnprocessable()->assertJsonValidationErrors('countries');
    $this->actingAsVendor($this->owner, $this->tenant)->postJson($markets, ['countries' => [999999]])->assertUnprocessable();

    $this->actingAsVendor($this->owner, $this->tenant)->postJson($markets, [
        'countries' => [$this->sa->id],
        'placements' => [$this->sa->id => ['trending', 'new_in']],
    ])->assertOk();

    $stored = $this->tenant->run(fn () => StoreProduct::query()->where('central_product_id', $lamp->id)->with('markets')->sole());
    expect($stored->markets)->toHaveCount(1)
        ->and($stored->markets[0]->country_id)->toBe($this->sa->id)
        ->and($stored->markets[0]->placements)->toBe(['trending', 'new_in']);

    $this->actingAsVendor($this->owner, $this->tenant)->get($markets)->assertOk()->assertSee(__('catalog::vendor.opportunities.added_in'))->assertSee(__('catalog::vendor.opportunities.edit_markets'));
    $this->actingAsVendor($this->owner, $this->tenant)->get($markets.'?edit=1')->assertOk()->assertSee('data-markets-form', false);

    expect($this->tenant->run(fn () => StoreProductMarket::query()->count()))->toBe(1);
});
