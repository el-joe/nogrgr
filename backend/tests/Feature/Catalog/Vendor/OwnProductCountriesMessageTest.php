<?php

use App\Modules\Catalog\Models\Category;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/VendorCatalogHelpers.php';

/*
| RQA-VEN-008: an own product without countries (and "All countries" unticked) answers a
| translated sentence, never the raw `required_unless` text "... unless all countries is in 1, true".
*/

beforeEach(function (): void {
    Storage::fake('public');
    [$this->tenant, $this->owner] = catalogStore();
    $this->category = Category::factory()->create();
    $this->url = $this->tenantUrl($this->tenant, 'admin/catalog/own-products');
});

it('explains the missing countries in the request locale', function (string $locale): void {
    app()->setLocale($locale);
    $expected = trans('catalog::vendor.own.countries_required', [], $locale);

    $this->actingAsVendor($this->owner, $this->tenant)
        ->withHeader('Accept-Language', $locale)
        ->postJson($this->url, ['name' => ['en' => 'Mug'], 'category_ids' => [$this->category->id], 'price' => '10.00', 'country_ids' => []])
        ->assertUnprocessable()
        ->assertJsonPath('errors.country_ids.0', $expected);

    expect($expected)->not->toContain('1, true')->not->toBe('catalog::vendor.own.countries_required');
})->with(['en', 'ar']);
