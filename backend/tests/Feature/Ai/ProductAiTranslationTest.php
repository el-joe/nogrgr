<?php

use App\Modules\Ai\Enums\AiFeature;
use App\Modules\Ai\Models\AiUsageLog;
use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductOverride;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Models\VariantOverride;
use App\Modules\Integrations\Enums\IntegrationSection;
use App\Modules\Localization\Models\ContentTranslationLock;
use App\Modules\Localization\Models\Language;
use App\Modules\Localization\Models\TenantLanguage;
use App\Modules\Localization\Services\TenantLanguageService;
use App\Shared\Money\Money;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

require_once __DIR__.'/../Catalog/Vendor/VendorCatalogHelpers.php';

/*
| VEN-012b "Translate with AI" on the vendor product page: custom provider (FreeLLMAPI,
| decision #44 a), preview → apply, central products written only into the store
| override in the chosen language (#65), locks respected, usage logged.
*/

beforeEach(function (): void {
    Sleep::fake();
    Http::preventStrayRequests();
    $this->useSettings(IntegrationSection::Ai, [
        'custom_provider' => 'freellmapi',
        'paid_provider' => 'openai',
        'freellmapi_base_url' => 'http://free-llm.test/v1',
        'freellmapi_model' => 'auto',
    ]);
    $this->calls = 0;
    $this->down = false;
    Http::fake(['http://free-llm.test/v1/*' => function (HttpRequest $request) {
        $this->calls++;

        if ($this->down) {
            return Http::response(['error' => ['message' => 'down']], 503);
        }

        $texts = json_decode($request['messages'][1]['content'], true)['texts'];

        return Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => json_encode(['translations' => array_map(static fn (string $text): string => '[fr] '.$text, $texts)], JSON_UNESCAPED_UNICODE)]]],
            'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 8],
        ]);
    }]);

    [$this->tenant, $this->owner] = catalogStore('pat-'.Str::lower(Str::random(6)));
    $french = Language::query()->where('code', 'fr')->first() ?? Language::factory()->create(['code' => 'fr', 'name' => ['en' => 'French', 'ar' => 'الفرنسية'], 'native_name' => 'Français']);
    $french->update(['is_active' => true]);
    $this->tenant->run(function (): void {
        app(TenantLanguageService::class)->enableCoreLanguages();
        TenantLanguage::query()->create(['language_code' => 'fr', 'is_enabled' => true, 'is_default' => false, 'sort_order' => 9, 'source' => 'purchased', 'enabled_at' => now()]);
    });
    $this->central = Product::factory()->create(['name' => ['en' => 'Wireless Earbuds', 'ar' => 'سماعات'], 'summary' => ['en' => 'Only {{ count }} left'], 'description' => null, 'meta_title' => null, 'meta_description' => null, 'base_price' => Money::of('24.00')]);
    $this->variant = ProductVariant::factory()->create(['product_id' => $this->central->id, 'title' => ['en' => 'Black']]);
    $this->product = storeProduct($this->tenant, $this->central->id);
    $this->url = fn (string $path) => $this->tenantUrl($this->tenant, 'admin/catalog/products/'.$this->product->id.$path);
});

it('shows the content tab with the texts per store language and the Translate with AI modal', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)('/edit?tab=content'))
        ->assertOk()
        ->assertSee(__('ai::product_translation.tab'))
        ->assertSee(__('ai::product_translation.translate'))
        ->assertSee('data-ai-translate', false)
        ->assertSee('data-ai-preview-field="fr|product|name"', false)
        ->assertSee('data-ai-preview-field="fr|variant:'.$this->variant->id.'|title"', false)
        // Central English text is not offered for AI (it changes through an edit request).
        ->assertDontSee('data-ai-preview-field="en|product|name"', false)
        ->assertSee('Wireless Earbuds');
});

it('previews without saving, then writes only the store override in the chosen language', function (): void {
    $preview = $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('/ai-translate/preview'), ['locales' => ['fr']])
        ->assertOk()
        ->json('data.translations');

    expect($preview['fr']['product']['name'])->toBe('[fr] Wireless Earbuds')
        ->and($preview['fr']['product']['summary'])->toBe('[fr] Only {{ count }} left')
        ->and($preview['fr']['variant:'.$this->variant->id]['title'])->toBe('[fr] Black');
    $this->tenant->run(fn () => expect(ProductOverride::query()->count())->toBe(0));

    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('/ai-translate/apply'), ['translations' => ['fr' => [
        'product' => ['name' => 'Écouteurs sans fil', 'summary' => '[fr] Only {{ count }} left', 'sku' => 'ignored'],
        'variant:'.$this->variant->id => ['title' => 'Noir'],
        'variant:999999' => ['title' => 'Ghost'],
    ]]])->assertOk()->assertJsonPath('redirect', fn (string $url): bool => str_contains($url, 'tab=content'));

    $this->tenant->run(function (): void {
        $override = ProductOverride::query()->where('product_id', $this->central->id)->first();
        expect($override->getTranslation('name', 'fr', false))->toBe('Écouteurs sans fil')
            ->and($override->getTranslation('name', 'en', false))->toBe('')
            ->and(VariantOverride::query()->where('variant_id', $this->variant->id)->first()->getTranslation('title', 'fr', false))->toBe('Noir')
            ->and(VariantOverride::query()->count())->toBe(1);
    });
    expect($this->central->fresh()->getTranslation('name', 'fr', false))->toBe('');

    $log = AiUsageLog::withoutTenantScope()->latest('id')->first();
    expect($log->feature)->toBe(AiFeature::ProductTranslation)
        ->and($log->tenant_id)->toBe((int) $this->tenant->id)
        ->and($log->provider)->toBe('freellmapi')
        ->and($log->prompt_tokens)->toBe(12);
});

it('never sends or writes fields hand-locked for the language', function (): void {
    $this->tenant->run(fn () => ContentTranslationLock::query()->create(['content_type' => 'product_overrides', 'content_id' => $this->central->id, 'field' => 'name', 'locale' => 'fr']));

    $preview = $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('/ai-translate/preview'), ['locales' => ['fr']])->assertOk()->json('data.translations');
    expect($preview['fr']['product'])->not->toHaveKey('name');

    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('/ai-translate/apply'), ['translations' => ['fr' => ['product' => ['name' => 'Forced']]]])
        ->assertUnprocessable();
});

it('translates own products into their own row', function (): void {
    $own = $this->tenant->run(fn (): StoreProduct => StoreProduct::factory()->create(['source' => StoreProductSource::Own, 'name' => ['en' => 'Desk lamp'], 'summary' => null, 'description' => null, 'label' => null, 'meta_title' => null, 'meta_description' => null]));
    $url = $this->tenantUrl($this->tenant, 'admin/catalog/own-products/'.$own->id.'/ai-translate');

    $this->actingAsVendor($this->owner, $this->tenant)->postJson($url.'/preview', ['locales' => ['fr', 'ar']])
        ->assertOk()
        ->assertJsonPath('data.translations.fr.product.name', '[fr] Desk lamp')
        ->assertJsonPath('data.translations.ar.product.name', '[fr] Desk lamp');
    $this->actingAsVendor($this->owner, $this->tenant)->postJson($url.'/apply', ['translations' => ['fr' => ['product' => ['name' => 'Lampe']]]])->assertOk();

    $this->tenant->run(fn () => expect(StoreProduct::query()->find($own->id)->getTranslation('name', 'fr', false))->toBe('Lampe'));
});

it('guards permissions, languages and provider failures', function (): void {
    $viewer = catalogStaff($this->tenant, ['catalog.products.view']);
    $this->actingAsVendor($viewer, $this->tenant)->postJson(($this->url)('/ai-translate/preview'), ['locales' => ['fr']])->assertForbidden();

    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('/ai-translate/preview'), ['locales' => []])->assertUnprocessable();
    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('/ai-translate/preview'), ['locales' => ['de']])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('ai::product_translation.errors.unknown_language', ['language' => 'de']));

    $this->down = true;
    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('/ai-translate/preview'), ['locales' => ['fr']])
        ->assertUnprocessable();
    $this->tenant->run(fn () => expect(ProductOverride::query()->count())->toBe(0));
});
