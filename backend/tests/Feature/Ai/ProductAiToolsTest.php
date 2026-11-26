<?php

use App\Modules\Ai\Enums\AiFeature;
use App\Modules\Ai\Models\AiUsageLog;
use App\Modules\Ai\Services\PriceFinder\PriceFinderService;
use App\Modules\Ai\Services\SocialPosts\SocialPostService;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Services\TenantLanguageService;
use App\Shared\Money\Money;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

require_once __DIR__.'/../Catalog/Vendor/VendorCatalogHelpers.php';

/*
| VEN-010b AI price finder + VEN-010c AI social posts (OpenAI only, Http::fake): monthly
| `ai_calls` quota, one usage row per call, suggestion never applied, permissions,
| tenant isolation.
*/

beforeEach(function (): void {
    Sleep::fake();
    Http::preventStrayRequests();
    config(['ai.providers.openai.api_key' => 'sk-test-key', 'ai.providers.openai.base_url' => 'https://api.openai.com/v1', 'ai.providers.openai.model' => 'gpt-test']);
    $this->down = false;
    $down = fn () => $this->down ? Http::response(['error' => ['message' => 'bad key', 'code' => 'invalid_api_key']], 401) : null;
    Http::fake([
        'api.openai.com/v1/responses' => fn () => $down() ?? Http::response([
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Here: [{"source":"Amazon.sa","title":"Earbuds X","price_usd":64.00,"url":"https://amazon.sa/x"},{"source":"Noon","title":"Pro Buds","price_usd":59,"url":"https://noon.com/y"},{"source":"Jarir","title":"ANC Buds","price_usd":"69.00","url":"https://jarir.com/z"},{"source":"Bad","title":"no url","price_usd":10,"url":"ftp://x"}]']]]],
            'usage' => ['input_tokens' => 300, 'output_tokens' => 80],
        ]),
        'api.openai.com/v1/chat/completions' => fn () => $down() ?? Http::response([
            'choices' => [['message' => ['content' => json_encode(['caption' => 'Silence the noise, keep the music.', 'hashtags' => ['earbuds', '#music', 'earbuds']])]]],
            'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 30],
        ]),
    ]);

    [$this->tenant, $this->owner, $this->sa] = catalogStore('aitools-'.Str::lower(Str::random(6)));
    $this->tenant->run(fn () => app(TenantLanguageService::class)->enableCoreLanguages());
    $this->central = Product::factory()->create(['name' => ['en' => 'Wireless Earbuds Pro'], 'base_price' => Money::of('24.00')]);
    $this->product = storeProduct($this->tenant, $this->central->id);
    $this->url = fn (string $path) => $this->tenantUrl($this->tenant, 'admin/catalog/products/'.$this->product->id.$path);
});

it('finds market prices for a selling country as a suggestion only', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant)
        ->postJson(($this->url)('/ai-price'), ['country_id' => $this->sa->id])
        ->assertOk()
        ->assertJsonPath('data.result.sample_size', 3)
        ->assertJsonPath('data.result.median', 6400)
        ->assertJsonPath('data.result.suggested', 6400)
        ->assertJsonPath('data.result.min', 5900)
        ->assertJsonPath('data.result.max', 6900)
        ->assertJsonPath('data.result.average', 6400)
        ->assertJsonPath('data.result.country.iso2', 'SA')
        ->assertJsonPath('data.quota.max', null);

    Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/responses')
        && $request['tools'][0]['type'] === 'web_search'
        && $request['tools'][0]['user_location']['country'] === 'SA'
        && str_contains($request['input'], 'Wireless Earbuds Pro'));

    // Nothing applied to the store product.
    $this->tenant->run(fn () => expect(StoreProduct::query()->find($this->product->id)->markup)->toBe($this->product->markup));
    $log = AiUsageLog::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->sole();
    expect($log->feature)->toBe(AiFeature::PriceFinder)->and($log->prompt_tokens)->toBe(300)->and($log->completion_tokens)->toBe(80)->and($log->model)->toBe('gpt-test');
});

it('only searches the store selling countries', function (): void {
    $fr = Country::query()->where('iso2', 'GB')->first() ?? Country::factory()->iso('GB')->create();

    $this->actingAsVendor($this->owner, $this->tenant)
        ->postJson(($this->url)('/ai-price'), ['country_id' => $fr->id])
        ->assertUnprocessable()->assertJsonValidationErrors(['country_id']);
    Http::assertNothingSent();
});

it('generates a social post per platform and language, trimmed to the platform limit', function (): void {
    $response = $this->actingAsVendor($this->owner, $this->tenant)
        ->postJson(($this->url)('/ai-social'), ['platform' => 'x', 'locale' => 'en'])
        ->assertOk()
        ->assertJsonPath('data.post.caption', 'Silence the noise, keep the music.')
        ->assertJsonPath('data.post.hashtags', ['#earbuds', '#music']);

    expect($response->json('data.post.text'))->toContain('/products/'.$this->product->id)->and(mb_strlen($response->json('data.post.text')))->toBeLessThanOrEqual(280);
    expect(AiUsageLog::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->sole()->feature)->toBe(AiFeature::SocialPost);

    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('/ai-social'), ['platform' => 'myspace', 'locale' => 'en'])->assertUnprocessable();
    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('/ai-social'), ['platform' => 'x', 'locale' => 'de'])->assertUnprocessable();
});

it('shares the monthly ai_calls quota between the tools and shows the limit reached state', function (): void {
    $plan = Plan::factory()->create(['limits' => ['ai_calls' => 2]]);
    Subscription::factory()->create(['tenant_id' => $this->tenant->id, 'plan_id' => $plan->id]);
    $vendor = $this->actingAsVendor($this->owner, $this->tenant);

    $vendor->postJson(($this->url)('/ai-price'), ['country_id' => $this->sa->id])->assertOk()->assertJsonPath('data.quota.remaining', 1);
    $vendor->postJson(($this->url)('/ai-social'), ['platform' => 'instagram', 'locale' => 'ar'])->assertOk()->assertJsonPath('data.quota.reached', true);
    $vendor->postJson(($this->url)('/ai-price'), ['country_id' => $this->sa->id])
        ->assertStatus(429)
        ->assertJsonPath('data.quota.reached', true)
        ->assertJsonPath('message', __('ai::tools.quota.reached', ['max' => 2, 'date' => now()->startOfMonth()->addMonth()->translatedFormat('j M Y')]));

    Http::assertSentCount(2);
    // Image searches are a separate quota.
    expect(AiUsageLog::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe(2);
});

it('refuses guests and staff without the manage permission', function (): void {
    $this->postJson(($this->url)('/ai-price'), ['country_id' => $this->sa->id])->assertUnauthorized();

    $viewer = catalogStaff($this->tenant, ['catalog.products.view']);
    $this->actingAsVendor($viewer, $this->tenant)->postJson(($this->url)('/ai-price'), ['country_id' => $this->sa->id])->assertForbidden();
    $this->actingAsVendor($viewer, $this->tenant)->postJson(($this->url)('/ai-social'), ['platform' => 'x', 'locale' => 'en'])->assertForbidden();
    Http::assertNothingSent();
});

it('keeps another store\'s products out of reach', function (): void {
    [$other] = catalogStore('aitools-o-'.Str::lower(Str::random(6)));
    $foreign = storeProduct($other, Product::factory()->create()->id);

    $this->actingAsVendor($this->owner, $this->tenant)
        ->postJson($this->tenantUrl($this->tenant, 'admin/catalog/products/'.($foreign->id + 1000).'/ai-price'), ['country_id' => $this->sa->id])
        ->assertNotFound();
    Http::assertNothingSent();
});

it('shows the tool buttons and modals on the product page only when OpenAI is configured', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)('/edit'))
        ->assertOk()
        ->assertSee('data-overlay-open="product-ai-price-modal"', false)
        ->assertSee('data-ai-social', false)
        ->assertSee(__('ai::tools.quota.unlimited'));

    config(['ai.providers.openai.api_key' => null]);
    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)('/edit'))->assertOk()->assertDontSee('product-ai-price-modal', false);
});

it('reports a provider outage as 503 without counting the call', function (): void {
    $this->down = true;

    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('/ai-price'), ['country_id' => $this->sa->id])->assertStatus(503);
    expect(AiUsageLog::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe(0);
});

it('summarises and composes deterministically', function (): void {
    $sa = Country::query()->where('iso2', 'SA')->first();
    $summary = PriceFinderService::summarise('q', $sa, [
        ['source' => 'a', 'title' => 'a', 'url' => 'https://a', 'price' => 1000],
        ['source' => 'b', 'title' => 'b', 'url' => 'https://b', 'price' => 3000],
    ]);
    expect($summary['median'])->toBe(2000)->and($summary['average'])->toBe(2000)->and($summary['labels']['min'])->toBe('$10.00');

    $text = SocialPostService::compose(str_repeat('a', 300), ['#one', '#two'], 'https://s.test/p/1', 280);
    expect(mb_strlen($text))->toBeLessThanOrEqual(280)->and($text)->toEndWith('https://s.test/p/1')->not->toContain('#one');
});
