<?php

use App\Modules\Ai\Enums\AiFeature;
use App\Modules\Ai\Jobs\IndexProductImages;
use App\Modules\Ai\Models\AiUsageLog;
use App\Modules\Ai\Models\ProductImageEmbedding;
use App\Modules\Ai\Models\StoreImageEmbedding;
use App\Modules\Ai\Services\ImageSearch\ImageEmbedder;
use App\Modules\Ai\Services\ImageSearch\ImageIndexService;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Catalog\Models\CatalogMedia;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Storefront\Database\Seeders\ThemeCatalogSeeder;
use App\Modules\Storefront\Services\StoreThemeService;
use App\Shared\Money\Money;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require_once __DIR__.'/../Commerce/StoreApiHelpers.php';

/*
| Search by image (P10): OpenAI vision description → embedding (Http::fake only), cosine
| ranking over the products the store sells (central index ⊕ store-own index, #65),
| monthly plan quota `image_searches`, usage log, storefront + store API endpoints.
*/

/**
 * 256-dim vector with the given leading values.
 *
 * @param  list<float>  $head
 * @return list<float>
 */
function imgVec(array $head): array
{
    return ImageEmbedder::normalize(array_pad(array_map('floatval', $head), ImageEmbedder::DIMENSIONS, 0.0));
}

function fakeOpenAiVision(array $query = [1, 0, 0]): void
{
    config(['ai.providers.openai.api_key' => 'sk-test-key', 'ai.providers.openai.base_url' => 'https://api.openai.com/v1']);
    Http::fake([
        'api.openai.com/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'A red leather sneaker with white sole.']]], 'usage' => ['prompt_tokens' => 90, 'completion_tokens' => 20]]),
        'api.openai.com/v1/embeddings' => Http::response(['data' => [['embedding' => imgVec($query)]], 'usage' => ['prompt_tokens' => 12]]),
    ]);
}

function imageShop(): object
{
    $shop = shopSetup('img-'.Str::lower(Str::random(6)));
    // Central product the store does NOT sell: perfect match, must never be returned (#65 scope).
    $shop->foreign = Product::factory()->create(['base_price' => Money::of('10.00'), 'stock' => 5]);
    ProductImageEmbedding::query()->create(['product_id' => $shop->foreign->id, 'path' => 'catalog/x.jpg', 'source_hash' => sha1('x'), 'description' => 'red sneaker', 'embedding' => imgVec([1, 0, 0]), 'dims' => 256, 'model' => 'test']);
    ProductImageEmbedding::query()->create(['product_id' => $shop->central->id, 'path' => 'catalog/c.jpg', 'source_hash' => sha1('c'), 'description' => 'red shoe', 'embedding' => imgVec([0.9, 0.3, 0]), 'dims' => 256, 'model' => 'test']);
    $shop->tenant->run(function () use ($shop): void {
        $shop->own->forceFill(['name' => 'Canvas Shoe'])->save();
        $shop->unrelated = StoreProduct::factory()->create(['name' => 'Desk Lamp', 'price' => Money::of('9.00'), 'stock' => 4]);
        StoreImageEmbedding::query()->create(['store_product_id' => $shop->own->id, 'path' => 'own/a.jpg', 'source_hash' => sha1('a'), 'description' => 'shoe', 'embedding' => imgVec([0.5, 0.86, 0]), 'dims' => 256, 'model' => 'test']);
        StoreImageEmbedding::query()->create(['store_product_id' => $shop->unrelated->id, 'path' => 'own/b.jpg', 'source_hash' => sha1('b'), 'description' => 'lamp', 'embedding' => imgVec([0, 0, 1]), 'dims' => 256, 'model' => 'test']);
    });
    tenancy()->end();

    return $shop;
}

it('ranks the store products by cosine similarity (golden order) and skips products the store does not sell', function (): void {
    fakeOpenAiVision();
    $shop = imageShop();

    $response = $this->withSession(['storefront.country_id' => $shop->sa->id])
        ->post($this->tenantUrl($shop->tenant, '/search/image'), ['image' => UploadedFile::fake()->image('shoe.jpg', 200, 200)], ['Accept' => 'application/json'])
        ->assertOk();

    $ids = array_column($response->json('data.products'), 'id');
    expect($ids)->toBe([$shop->catalog->id, $shop->own->id])
        ->and($response->json('data.products.0.match'))->toBe(95)
        ->and($response->json('data.products.1.match'))->toBe(50)
        ->and($response->json('data.products.0.price'))->toContain('30.00')
        ->and($response->json('data.total'))->toBe(2);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/chat/completions')
        && str_starts_with((string) data_get($request->data(), 'messages.1.content.0.image_url.url'), 'data:image/'));
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/embeddings') && $request['dimensions'] === 256);
});

it('logs one usage row per search with the tokens and the store', function (): void {
    fakeOpenAiVision();
    $shop = imageShop();

    $this->withSession(['storefront.country_id' => $shop->sa->id])
        ->post($this->tenantUrl($shop->tenant, '/search/image'), ['image' => UploadedFile::fake()->image('a.png')], ['Accept' => 'application/json'])
        ->assertOk();

    $log = AiUsageLog::query()->withoutGlobalScopes()->where('tenant_id', $shop->tenant->id)->sole();
    expect($log->feature)->toBe(AiFeature::ImageSearch)
        ->and($log->provider)->toBe('openai')
        ->and($log->prompt_tokens)->toBe(102)
        ->and($log->completion_tokens)->toBe(20);
});

it('enforces the monthly image search quota of the plan', function (): void {
    fakeOpenAiVision();
    $shop = imageShop();
    $plan = Plan::factory()->create(['limits' => ['image_searches' => 1]]);
    Subscription::factory()->create(['tenant_id' => $shop->tenant->id, 'plan_id' => $plan->id]);
    $send = fn () => $this->withSession(['storefront.country_id' => $shop->sa->id])
        ->post($this->tenantUrl($shop->tenant, '/search/image'), ['image' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json']);

    $send()->assertOk();
    $send()->assertStatus(429)->assertJsonPath('error', 'quota_reached')->assertJsonPath('message', __st('search.image.errors.quota'));

    expect(AiUsageLog::query()->withoutGlobalScopes()->where('tenant_id', $shop->tenant->id)->count())->toBe(1);
    Http::assertSentCount(2); // only the first search reached OpenAI (vision + embedding)
});

it('does not count last month nor other stores against the quota', function (): void {
    fakeOpenAiVision();
    $shop = imageShop();
    $plan = Plan::factory()->create(['limits' => ['image_searches' => 1]]);
    Subscription::factory()->create(['tenant_id' => $shop->tenant->id, 'plan_id' => $plan->id]);
    $other = $this->createTenant('img-o-'.Str::lower(Str::random(6)));
    AiUsageLog::query()->withoutGlobalScopes()->create(['tenant_id' => $other->id, 'feature' => AiFeature::ImageSearch, 'provider' => 'openai', 'model' => 'm']);
    $old = AiUsageLog::query()->withoutGlobalScopes()->create(['tenant_id' => $shop->tenant->id, 'feature' => AiFeature::ImageSearch, 'provider' => 'openai', 'model' => 'm']);
    $old->forceFill(['created_at' => now()->subMonthNoOverflow()->startOfMonth()])->save();

    $this->withSession(['storefront.country_id' => $shop->sa->id])
        ->post($this->tenantUrl($shop->tenant, '/search/image'), ['image' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])
        ->assertOk();
});

it('answers the mobile store API with the same JSON', function (): void {
    fakeOpenAiVision();
    $shop = imageShop();

    $this->post(storeApi('search/image'), ['image' => UploadedFile::fake()->image('a.jpg')], shopHeaders($shop->tenant))
        ->assertOk()
        ->assertJsonStructure(['data' => ['total', 'title', 'products' => [['id', 'name', 'url', 'image', 'price', 'price_minor', 'match']]]]);
});

it('validates the photo and refuses private image URLs without calling OpenAI', function (): void {
    fakeOpenAiVision();
    $shop = imageShop();
    $url = $this->tenantUrl($shop->tenant, '/search/image');

    $this->post($url, [], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors(['image', 'url']);
    $this->post($url, ['image' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors(['image']);
    $this->post($url, ['url' => 'http://example.com/a.jpg'], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors(['url']);
    $this->post($url, ['url' => 'https://127.0.0.1/a.jpg'], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonPath('error', 'invalid_image');

    Http::assertNothingSent();
});

it('reports a provider outage as unavailable and hides the camera button without OpenAI', function (): void {
    $shop = imageShop();
    config(['ai.providers.openai.api_key' => null]);

    $this->post($this->tenantUrl($shop->tenant, '/search/image'), ['image' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])
        ->assertStatus(503)->assertJsonPath('error', 'unavailable');
    $this->withSession(['storefront.country_id' => $shop->sa->id])->get($this->tenantUrl($shop->tenant, '/'))->assertOk()->assertDontSee('data-image-search-open', false);

    config(['ai.providers.openai.api_key' => 'sk-test-key']);
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'down']], 500)]);
    $this->post($this->tenantUrl($shop->tenant, '/search/image'), ['image' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])
        ->assertStatus(503)->assertJsonPath('message', __st('search.image.errors.unavailable'));
});

it('renders the camera button and modal in every theme family', function (?string $theme): void {
    fakeOpenAiVision();
    Notification::fake();
    Storage::fake('central');
    $this->seed(ThemeCatalogSeeder::class);
    $shop = imageShop();

    if ($theme !== null) {
        $shop->tenant->run(fn () => app(StoreThemeService::class)->activate($theme));
        tenancy()->end();
    }

    $html = $this->withSession(['storefront.country_id' => $shop->sa->id])->get($this->tenantUrl($shop->tenant, '/'))->assertOk()->getContent();

    expect($html)->toContain('data-image-search-open')->toContain('id="sf-image-search"')->toContain(__st('search.image.drop'));
})->with(['ecommet' => null, 'elora' => 'elora-1', 'souqify' => 'souqify-1']);

it('indexes central and own product images once and prunes removed images', function (): void {
    fakeOpenAiVision([0, 1, 0]);
    Storage::fake('public');
    $shop = shopSetup('img-ix-'.Str::lower(Str::random(6)));
    Storage::disk('public')->put('catalog/p1.jpg', UploadedFile::fake()->image('p1.jpg')->getContent());
    $media = $shop->central->media()->create(['disk' => 'public', 'path' => 'catalog/p1.jpg', 'mime_type' => 'image/jpeg', 'size' => 100, 'is_primary' => true, 'sort_order' => 0]);
    $index = app(ImageIndexService::class);

    expect($index->syncCentral($shop->central->id))->toBe(1)
        ->and($index->syncCentral($shop->central->id))->toBe(0); // unchanged → not re-sent
    expect(ProductImageEmbedding::query()->where('product_id', $shop->central->id)->sole()->description)->toBe('A red leather sneaker with white sole.');
    expect(AiUsageLog::query()->withoutGlobalScopes()->whereNull('tenant_id')->where('feature', AiFeature::ImageIndex->value)->count())->toBe(1);

    CatalogMedia::query()->whereKey($media->id)->delete();
    $index->syncCentral($shop->central->id);
    expect(ProductImageEmbedding::query()->where('product_id', $shop->central->id)->count())->toBe(0);

    $shop->tenant->run(function () use ($shop, $index): void {
        Storage::disk('public')->put('own/o1.jpg', UploadedFile::fake()->image('o1.jpg')->getContent());
        $shop->own->media()->create(['disk' => 'public', 'path' => 'own/o1.jpg', 'mime_type' => 'image/jpeg', 'size' => 100, 'is_primary' => true, 'sort_order' => 0]);
        expect($index->syncOwn($shop->own->id))->toBe(1)
            ->and(StoreImageEmbedding::query()->where('store_product_id', $shop->own->id)->count())->toBe(1);
    });
});

it('queues the backfill command only when OpenAI is configured', function (): void {
    Queue::fake();
    config(['ai.providers.openai.api_key' => null]);
    $this->artisan('ai:index-product-images', ['--central-only' => true])->assertFailed();

    config(['ai.providers.openai.api_key' => 'sk-test-key']);
    Product::factory()->create();
    $this->artisan('ai:index-product-images', ['--central-only' => true])->assertSuccessful();
    Queue::assertPushed(IndexProductImages::class);
});
