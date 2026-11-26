<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Storefront\Services\StorefrontContext;
use App\Shared\Money\Money;

require_once __DIR__.'/../Commerce/StoreApiHelpers.php';

/*
| Storefront catalog (sf-catalog): home, listings, category, product, search, sitemap on a
| store selling in SA + AE (shopSetup): a central product (24.00 + 20 % markup + SA fixed
| shipping 1.20 → $30.00 in SA) and an own product ($15.00).
*/

function catalogShop(string $slug = 'shop'): object
{
    $shop = shopSetup($slug);
    $category = Category::factory()->create(['slug' => 'gadgets-'.$slug, 'name' => 'Gadgets']);
    $shop->central->categories()->attach($category->id);
    $shop->tenant->run(function () use ($shop, $category): void {
        $shop->own->forceFill(['name' => 'Desk Lamp', 'category_ids' => [$category->id]])->save();
    });
    $shop->category = $category;

    return $shop;
}

function inSa(object $shop): array
{
    return ['storefront.country_id' => $shop->sa->id];
}

it('renders the home page with the store products, SEO and the context island', function (): void {
    $shop = catalogShop();

    $html = $this->withSession(inSa($shop))->get($this->tenantUrl($shop->tenant, '/'))
        ->assertOk()
        ->assertSee('id="sf-context"', false)
        ->assertSee('Desk Lamp')
        ->assertSee('"@type":"WebSite"', false)
        ->assertSee('"@type":"Organization"', false)
        ->assertSee('rel="canonical"', false)
        ->assertSee('hreflang="ar"', false)
        ->assertSee('hreflang="en"', false)
        ->getContent();

    expect($html)->toContain('og:title')->toContain('data-add-to-cart');
});

it('prices a central product like the cart in the shopping country', function (): void {
    $shop = catalogShop();

    // 24.00 cost + 4.80 profit + 1.20 SA fixed shipping.
    $this->withSession(inSa($shop))->get($this->tenantUrl($shop->tenant, '/new-in'))
        ->assertOk()
        ->assertSee('$30.00')
        ->assertSee('$15.00');
});

it('serves every listing page', function (string $path): void {
    $shop = catalogShop();

    $this->withSession(inSa($shop))->get($this->tenantUrl($shop->tenant, $path))->assertOk()->assertSee('data-sf-page="listing-', false);
})->with(['/best-selling', '/new-in', '/top-rated', '/offers', '/search?q=lamp', '/best-selling?period=7&sort=price_asc']);

it('shows the empty states of listings without data', function (): void {
    $shop = catalogShop();

    $this->withSession(inSa($shop))->get($this->tenantUrl($shop->tenant, '/top-rated'))->assertOk()->assertSee('No rated products yet');
    $this->withSession(inSa($shop))->get($this->tenantUrl($shop->tenant, '/search?q=zzzz-nothing'))->assertOk()->assertSee('No results for');
});

it('answers load more with a JSON fragment of the next page', function (): void {
    $shop = catalogShop();
    $shop->tenant->run(fn () => StoreProduct::factory()->count(3)->create(['price' => Money::of('9.00'), 'stock' => 3]));

    $this->withSession(inSa($shop))->getJson($this->tenantUrl($shop->tenant, '/new-in?per=15&page=1&fragment=1'))
        ->assertOk()
        ->assertJsonStructure(['html', 'next_url', 'total', 'page'])
        ->assertJsonPath('total', 5)
        ->assertJsonPath('next_url', null);
});

it('filters listings by price, stock and category', function (): void {
    $shop = catalogShop();

    $this->withSession(inSa($shop))->get($this->tenantUrl($shop->tenant, '/new-in?price=0-2000'))
        ->assertOk()->assertSee('Desk Lamp')->assertDontSee('$30.00');

    $this->withSession(inSa($shop))->get($this->tenantUrl($shop->tenant, '/category/'.$shop->category->slug))
        ->assertOk()
        ->assertSee('Gadgets')
        ->assertSee('Desk Lamp')
        ->assertSee('"@type":"BreadcrumbList"', false);

    $this->withSession(inSa($shop))->get($this->tenantUrl($shop->tenant, '/category/does-not-exist'))->assertNotFound();
});

it('renders the categories browser', function (): void {
    $shop = catalogShop();

    $this->withSession(inSa($shop))->get($this->tenantUrl($shop->tenant, '/categories'))->assertOk()->assertSee('Gadgets');
});

it('renders the product page with Product / Offer JSON-LD in the shopping country', function (): void {
    $shop = catalogShop();
    $slug = $shop->tenant->run(fn () => $shop->catalog->fresh()->slug) ?: $shop->central->slug;

    $response = $this->withSession(inSa($shop))->get($this->tenantUrl($shop->tenant, '/products/'.$shop->catalog->id.'/'.$slug))
        ->assertOk()
        ->assertSee('data-product-page', false)
        ->assertSee('"@type":"Product"', false)
        ->assertSee('"@type":"Offer"', false)
        ->assertSee('"price":30', false)
        ->assertSee('"priceCurrency":"USD"', false)
        ->assertSee('https://schema.org/InStock', false);

    expect($response->getContent())->toContain('id="sf-product"');
});

it('redirects a product URL without the canonical slug', function (): void {
    $shop = catalogShop();

    $this->withSession(inSa($shop))->get($this->tenantUrl($shop->tenant, '/products/'.$shop->own->id))
        ->assertMovedPermanently();
});

it('returns 404 for products not sold in the shopping country or inactive', function (): void {
    $shop = catalogShop();
    $shop->central->forceFill(['allowed_country_ids' => [$shop->ae->id]])->save();

    $this->withSession(inSa($shop))->get($this->tenantUrl($shop->tenant, '/products/'.$shop->catalog->id.'/x'))->assertNotFound();

    $shop->tenant->run(fn () => $shop->own->forceFill(['is_active' => false])->save());
    $this->withSession(inSa($shop))->get($this->tenantUrl($shop->tenant, '/products/'.$shop->own->id.'/x'))->assertNotFound();
    $this->withSession(inSa($shop))->get($this->tenantUrl($shop->tenant, '/products/999999/x'))->assertNotFound();
});

it('hides products limited to other countries from listings', function (): void {
    $shop = catalogShop();
    $shop->tenant->run(fn () => $shop->own->forceFill(['country_ids' => [$shop->ae->id]])->save());

    $this->withSession([StorefrontContext::COUNTRY_SESSION_KEY => $shop->sa->id])->get($this->tenantUrl($shop->tenant, '/new-in'))
        ->assertOk()
        ->assertDontSee('Desk Lamp')
        ->assertSee('$30.00');
});

it('suggests products and categories in the search autocomplete', function (): void {
    $shop = catalogShop();

    $this->withSession(inSa($shop))->getJson($this->tenantUrl($shop->tenant, '/search/autocomplete?q=desk'))
        ->assertOk()
        ->assertJsonPath('data.products.0.name', 'Desk Lamp')
        ->assertJsonPath('data.products.0.price', '$15.00');

    $this->withSession(inSa($shop))->getJson($this->tenantUrl($shop->tenant, '/search/autocomplete?q=gadg'))
        ->assertOk()
        ->assertJsonPath('data.categories.0.name', 'Gadgets');

    $this->withSession(inSa($shop))->getJson($this->tenantUrl($shop->tenant, '/search/autocomplete?q=d'))
        ->assertOk()
        ->assertJsonPath('data.products', []);
});

it('serves the home tab fragment and 404s an unknown tab', function (): void {
    $shop = catalogShop();

    $this->withSession(inSa($shop))->getJson($this->tenantUrl($shop->tenant, '/home/tab/recommended'))->assertOk()->assertJsonStructure(['html', 'next_url']);
    $this->withSession(inSa($shop))->getJson($this->tenantUrl($shop->tenant, '/home/tab/'.$shop->category->slug))->assertOk();
    $this->withSession(inSa($shop))->getJson($this->tenantUrl($shop->tenant, '/home/tab/unknown-tab'))->assertNotFound();
});

it('lists home, listings, categories and sellable products in the sitemap', function (): void {
    $shop = catalogShop();

    $xml = $this->withSession(inSa($shop))->get($this->tenantUrl($shop->tenant, '/sitemap.xml'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->getContent();

    expect($xml)
        ->toContain('<urlset')
        ->toContain('/best-selling</loc>')
        ->toContain('/category/'.$shop->category->slug.'</loc>')
        ->toContain('/products/'.$shop->own->id.'/')
        ->toContain('hreflang="ar"');

    expect($this->get($this->tenantUrl($shop->tenant, '/robots.txt'))->getContent())->toContain('Sitemap: ');
});
