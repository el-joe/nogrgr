<?php

use App\Modules\Catalog\Enums\MediaType;
use App\Modules\Catalog\Models\CatalogMedia;
use App\Modules\Catalog\Models\ProductOverride;
use App\Modules\Storefront\Database\Seeders\ThemeCatalogSeeder;
use App\Modules\Storefront\Services\StoreThemeService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Commerce/StoreApiHelpers.php';

/*
| Product gallery videos on the storefront (#114): every theme family renders the video
| slide (native player, poster, contained) + the floating popup mount data, the Store
| API exposes `media` [{type, url, poster}], and store override images never drop the
| central videos.
*/

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('public');
    Storage::fake('central');
    $this->shop = shopSetup();
    $central = $this->shop->central;
    $central->gallery()->create(['type' => MediaType::Image, 'disk' => 'public', 'path' => 'catalog/products/a.png', 'mime_type' => 'image/png', 'size' => 10, 'is_primary' => true, 'sort_order' => 0]);
    $this->video = $central->gallery()->create(['type' => MediaType::Video, 'disk' => 'public', 'path' => 'catalog/products/clip.mp4', 'poster_path' => 'catalog/products/clip-poster.jpg', 'mime_type' => 'video/mp4', 'size' => 10, 'sort_order' => 1]);
    $this->sa = ['storefront.country_id' => $this->shop->sa->id];
});

function productPage(object $test, string $url): string
{
    $response = $test->withSession($test->sa)->get($url);
    $response = $response->isRedirect() ? $test->withSession($test->sa)->get($response->headers->get('Location')) : $response;

    return (string) $response->assertOk()->getContent();
}

it('renders the gallery video slide and the floating popup data in every theme family', function (string $theme, string $family): void {
    $this->seed(ThemeCatalogSeeder::class);
    $this->shop->tenant->run(fn () => app(StoreThemeService::class)->activate($theme));
    tenancy()->end();

    $html = productPage($this, $this->tenantUrl($this->shop->tenant, '/products/'.$this->shop->catalog->id));
    $video = $this->video->url();

    expect($html)->toContain('data-gallery-video-slide')
        ->toContain('src="'.$video.'"')
        ->toContain('poster="'.$this->video->posterUrl().'"')
        ->toContain('controls playsinline preload="metadata"')
        ->toContain('object-contain')
        ->toContain('data-video-pip=')
        ->toContain('&quot;family&quot;:&quot;'.$family.'&quot;')
        ->toContain('sf-video-thumb')
        ->and(substr_count($html, 'class="swiper-slide'))->toBeGreaterThanOrEqual(2);
})->with([
    'ecommet' => ['ecommet', 'ecommet'],
    'elora' => ['elora-1', 'elora'],
    'souqify' => ['souqify-1', 'souqify'],
]);

it('keeps the image-only gallery and no popup for products without a video', function (): void {
    CatalogMedia::query()->whereKey($this->video->id)->delete();

    $html = productPage($this, $this->tenantUrl($this->shop->tenant, '/products/'.$this->shop->catalog->id));

    expect($html)->not->toContain('data-gallery-video-slide')->not->toContain('data-video-pip=');
});

it('exposes the gallery media with types and posters on the Store API', function (): void {
    $this->getJson(storeApi('products/'.$this->shop->catalog->id), shopHeaders($this->shop->tenant) + ['X-Country' => 'SA'])
        ->assertOk()
        ->assertJsonCount(1, 'data.images')
        ->assertJsonPath('data.media.0.type', 'image')
        ->assertJsonPath('data.media.0.poster', null)
        ->assertJsonPath('data.media.1', ['type' => 'video', 'url' => $this->video->url(), 'poster' => $this->video->posterUrl()]);
});

it('keeps the central videos when the store overrides the images', function (): void {
    $this->shop->tenant->run(fn () => ProductOverride::query()->create(['product_id' => $this->shop->central->id, 'images' => ['tenant/override.png']]));

    $media = $this->getJson(storeApi('products/'.$this->shop->catalog->id), shopHeaders($this->shop->tenant) + ['X-Country' => 'SA'])
        ->assertOk()
        ->json('data.media');

    expect(array_column($media, 'type'))->toBe(['image', 'video'])
        ->and($media[0]['url'])->toEndWith('tenant/override.png')
        ->and($media[1]['url'])->toBe($this->video->url());
});
