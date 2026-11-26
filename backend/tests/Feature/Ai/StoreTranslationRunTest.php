<?php

use App\Modules\Ai\Enums\StoreTranslationSection;
use App\Modules\Ai\Enums\TranslationItemStatus;
use App\Modules\Ai\Models\AiUsageLog;
use App\Modules\Ai\Models\StoreTranslationItem;
use App\Modules\Ai\Notifications\StoreTranslationRunNotification;
use App\Modules\Ai\Services\StoreTranslation\DeltaTranslationService;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductOverride;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\StoreCategory;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Models\StoreProductVariant;
use App\Modules\Catalog\Models\VariantOverride;
use App\Modules\Commerce\Models\StoreFlashSale;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Integrations\Enums\IntegrationSection;
use App\Modules\Integrations\Repositories\Contracts\SettingsRepositoryContract;
use App\Modules\Localization\Enums\StaticTextSource;
use App\Modules\Localization\Enums\TranslationRunStatus;
use App\Modules\Localization\Enums\TranslationRunTrigger;
use App\Modules\Localization\Models\ContentTranslationLock;
use App\Modules\Localization\Models\Language;
use App\Modules\Localization\Models\StoreTranslationRun;
use App\Modules\Localization\Models\TenantLanguage;
use App\Modules\Localization\Models\TenantStaticText;
use App\Modules\Localization\Services\StaticTexts\StaticTextCatalog;
use App\Modules\Localization\Services\StoreLanguageService;
use App\Modules\Localization\Services\TenantLanguageService;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Models\StoreEmailTemplate;
use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Repositories\Contracts\StoreSettingRepositoryContract;
use App\Modules\Storefront\Models\StoreBanner;
use App\Modules\Storefront\Models\StoreMenuLink;
use App\Modules\Storefront\Models\StorePage;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

/*
| P10 paid full-store AI translation run (decisions #44 / #45): golden run over every
| section, locks and manual static texts skipped, DB credentials of the paid provider,
| resume after a provider failure, language activation, owner notification.
*/

beforeEach(function (): void {
    Sleep::fake();
    Notification::fake();
    Http::preventStrayRequests();

    $this->useSettings(IntegrationSection::Ai, [
        'provider' => 'freellmapi',
        'custom_provider' => 'freellmapi',
        'paid_provider' => 'openai',
        'openai_base_url' => 'https://paid-ai.test/v1',
        'openai_api_key' => 'sk-db-paid-key',
        'openai_model' => 'gpt-db-model',
        'freellmapi_base_url' => 'http://free-llm.test/v1',
        'freellmapi_model' => 'auto',
    ]);

    $this->tenant = $this->createTenant('str-'.Str::lower(Str::random(6)));
    $this->tenant->run(fn () => app(TenantLanguageService::class)->enableCoreLanguages());
    $this->owner = $this->createVendorOwner($this->tenant, ['email' => 'owner-'.Str::lower(Str::random(6)).'@megz.test']);
    $this->french = Language::query()->where('code', 'fr')->first() ?? Language::factory()->create(['code' => 'fr', 'name' => ['en' => 'French', 'ar' => 'الفرنسية'], 'native_name' => 'Français']);
    $this->french->update(['is_active' => true, 'price_minor' => 4900, 'country_ids' => null]);
    $this->calls = 0;
    $this->failFrom = null;
    $this->sent = [];
});

/**
 * Fake paid provider: "[fr] <text>" for every text; from call #failFrom on it answers 401.
 */
function fakePaidProvider(object $test): void
{
    Http::fake(['https://paid-ai.test/v1/*' => function (HttpRequest $request) use ($test) {
        $test->calls++;

        if ($test->failFrom !== null && $test->calls >= $test->failFrom) {
            return Http::response(['error' => ['message' => 'bad key', 'code' => 'invalid_api_key']], 401);
        }

        $texts = json_decode($request['messages'][1]['content'], true)['texts'];
        array_push($test->sent, ...array_values($texts));

        return Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => json_encode(['translations' => array_map(static fn (string $text): string => '[fr] '.$text, $texts)], JSON_UNESCAPED_UNICODE)]]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 7],
        ]);
    }]);
}

/**
 * One record of every translatable section of the store.
 *
 * @return array<string, int>
 */
function seedStoreContent(object $test): array
{
    $central = Product::factory()->create(['name' => ['en' => 'Central lamp', 'ar' => 'مصباح'], 'summary' => ['en' => 'Warm light'], 'description' => null, 'meta_title' => null, 'meta_description' => null]);
    $centralVariant = ProductVariant::factory()->create(['product_id' => $central->id, 'title' => ['en' => 'Large']]);

    return $test->tenant->run(function () use ($central, $centralVariant): array {
        $category = StoreCategory::factory()->create(['name' => ['en' => 'Shoes', 'ar' => 'أحذية'], 'description' => ['en' => 'All shoes'], 'meta_keywords' => null, 'meta_description' => null]);
        $locked = StoreCategory::factory()->create(['name' => ['en' => 'Bags', 'fr' => 'Sacs main'], 'description' => ['en' => 'Bags and more'], 'meta_keywords' => null, 'meta_description' => null]);
        ContentTranslationLock::query()->create(['content_type' => 'category', 'content_id' => $locked->id, 'field' => 'name', 'locale' => 'fr']);
        $product = StoreProduct::factory()->create(['name' => ['en' => 'Red shoe'], 'summary' => ['en' => 'Only {{ size }} left'], 'description' => ['ar' => 'وصف عربي']]);
        $variant = StoreProductVariant::query()->create(['store_product_id' => $product->id, 'title' => ['en' => 'Size 42']]);
        StoreProduct::factory()->central($central->id)->create();
        $sale = StoreFlashSale::factory()->create(['title' => ['en' => 'Summer sale']]);
        $banner = StoreBanner::query()->create(['title' => ['en' => 'Welcome'], 'subtitle' => ['en' => 'New in'], 'cta_label' => ['en' => 'Shop now'], 'image_path' => 'banners/a.png']);
        $link = StoreMenuLink::query()->create(['placement' => 'header', 'label' => ['en' => 'About us'], 'url' => '/about']);
        $page = StorePage::query()->create(['slug' => 'story-'.Str::lower(Str::random(4)), 'title' => ['en' => 'Our story'], 'body' => ['en' => '<p>Since <strong>2020</strong>, see https://example.test</p>']]);
        $template = StoreEmailTemplate::query()->updateOrCreate(['event' => EmailTemplateEvent::cases()[0]], ['subject' => ['en' => 'Order {{ order_number }}'], 'body' => ['en' => '<p>Hi {{ name }}</p>']]);
        app(StoreSettingRepositoryContract::class)->put(StoreSettingKey::FooterSettings, ['about' => ['en' => 'We love shoes'], 'copyright' => ['en' => 'All rights reserved'], 'show_social' => true]);

        $keys = app(StaticTextCatalog::class)->keys();
        TenantStaticText::query()->updateOrCreate(['key' => $keys[0], 'locale' => 'fr'], ['value' => 'Mon texte', 'source' => StaticTextSource::Manual, 'is_locked' => true]);

        return [
            'category' => $category->id, 'locked' => $locked->id, 'product' => $product->id, 'variant' => $variant->id,
            'central' => $central->id, 'central_variant' => $centralVariant->id, 'sale' => $sale->id, 'banner' => $banner->id,
            'link' => $link->id, 'page' => $page->id, 'template' => $template->id,
        ];
    });
}

function queueFrenchRun(object $test): StoreTranslationRun
{
    return app(StoreLanguageService::class)->queueRun((int) $test->tenant->id, $test->french->id, 'fr', null);
}

it('translates every section of the store into the bought language and switches it on', function (): void {
    fakePaidProvider($this);
    $ids = seedStoreContent($this);

    $run = queueFrenchRun($this)->fresh();

    expect($run->status)->toBe(TranslationRunStatus::Completed)
        ->and($run->progress)->toBe(100)
        ->and($run->failed_items)->toBe(0)
        ->and($run->done_items)->toBe($run->total_items)
        ->and($run->started_at)->not->toBeNull()
        ->and($run->provider)->toBe('openai')
        ->and($run->model)->toBe('gpt-db-model')
        ->and($run->prompt_tokens)->toBe(10 * $this->calls);

    Http::assertSent(fn (HttpRequest $request): bool => str_starts_with($request->url(), 'https://paid-ai.test/v1/chat/completions')
        && $request->hasHeader('Authorization', 'Bearer sk-db-paid-key')
        && $request['model'] === 'gpt-db-model');

    expect(Product::query()->find($ids['central'])->getTranslation('name', 'fr', false))->toBe('');

    $this->tenant->run(function () use ($ids): void {
        expect(StoreCategory::query()->find($ids['category'])->getTranslation('name', 'fr', false))->toBe('[fr] Shoes')
            ->and(StoreCategory::query()->find($ids['locked'])->getTranslation('name', 'fr', false))->toBe('Sacs main')
            ->and(StoreCategory::query()->find($ids['locked'])->getTranslation('description', 'fr', false))->toBe('[fr] Bags and more')
            ->and(StoreProduct::query()->find($ids['product'])->getTranslation('summary', 'fr', false))->toBe('[fr] Only {{ size }} left')
            ->and(StoreProduct::query()->find($ids['product'])->getTranslation('description', 'fr', false))->toBe('[fr] وصف عربي')
            ->and(StoreProductVariant::query()->find($ids['variant'])->getTranslation('title', 'fr', false))->toBe('[fr] Size 42')
            ->and(ProductOverride::query()->where('product_id', $ids['central'])->first()->getTranslation('name', 'fr', false))->toBe('[fr] Central lamp')
            ->and(ProductOverride::query()->where('product_id', $ids['central'])->first()->getTranslation('name', 'en', false))->toBe('')
            ->and(VariantOverride::query()->where('variant_id', $ids['central_variant'])->first()->getTranslation('title', 'fr', false))->toBe('[fr] Large')
            ->and(StoreFlashSale::query()->find($ids['sale'])->getTranslation('title', 'fr', false))->toBe('[fr] Summer sale')
            ->and(StoreBanner::query()->find($ids['banner'])->getTranslation('cta_label', 'fr', false))->toBe('[fr] Shop now')
            ->and(StoreMenuLink::query()->find($ids['link'])->getTranslation('label', 'fr', false))->toBe('[fr] About us')
            ->and(StorePage::query()->find($ids['page'])->getTranslation('body', 'fr', false))->toBe('[fr] <p>Since <strong>2020</strong>, see https://example.test</p>')
            ->and(StoreEmailTemplate::query()->find($ids['template'])->getTranslation('subject', 'fr', false))->toBe('[fr] Order {{ order_number }}')
            ->and(app(StoreSettingRepositoryContract::class)->get(StoreSettingKey::FooterSettings)['about']['fr'])->toBe('[fr] We love shoes');

        $keys = app(StaticTextCatalog::class)->keys();
        expect(TenantStaticText::query()->where('key', $keys[0])->where('locale', 'fr')->value('value'))->toBe('Mon texte');
        $ai = TenantStaticText::query()->where('key', $keys[1])->where('locale', 'fr')->first();
        expect($ai->source)->toBe(StaticTextSource::Ai)
            ->and($ai->value)->toStartWith('[fr] ');

        expect(TenantLanguage::query()->where('language_code', 'fr')->value('is_enabled'))->toBeTrue();

        foreach ([StoreTranslationSection::Categories, StoreTranslationSection::ProductOverrides, StoreTranslationSection::StaticTexts, StoreTranslationSection::StoreSettings] as $section) {
            expect(StoreTranslationItem::query()->where('section', $section->value)->where('status', TranslationItemStatus::Done->value)->exists())->toBeTrue();
        }
    });

    expect($this->sent)->not->toContain('Bags')->not->toContain(app(StaticTextCatalog::class)->value(app(StaticTextCatalog::class)->keys()[0], 'en'));
    Notification::assertSentTo($this->owner, StoreTranslationRunNotification::class, fn (StoreTranslationRunNotification $notification): bool => $notification->eventName === 'translation_completed');
});

it('fails on a provider error, keeps finished items and resumes only the rest on retry', function (): void {
    $this->failFrom = 3;
    fakePaidProvider($this);
    $ids = seedStoreContent($this);

    $run = queueFrenchRun($this)->fresh();

    expect($run->status)->toBe(TranslationRunStatus::Failed)
        ->and($run->failed_items)->toBeGreaterThan(0)
        ->and($run->done_items)->toBeGreaterThan(0)
        ->and($run->last_error)->not->toBeNull();
    $this->tenant->run(fn () => expect(TenantLanguage::query()->where('language_code', 'fr')->exists())->toBeFalse()
        ->and(StoreCategory::query()->find($ids['category'])->getTranslation('name', 'fr', false))->toBe('[fr] Shoes'));
    Notification::assertSentTo($this->owner, StoreTranslationRunNotification::class, fn (StoreTranslationRunNotification $notification): bool => $notification->eventName === 'translation_failed');

    $this->failFrom = null;
    $this->sent = [];
    app(StoreLanguageService::class)->retry($this->tenant, 'fr');
    $run->refresh();

    expect($run->status)->toBe(TranslationRunStatus::Completed)
        ->and($run->failed_items)->toBe(0)
        ->and($run->attempts)->toBe(1)
        ->and($this->sent)->not->toContain('Shoes')
        ->and($this->sent)->toContain('Summer sale');
    $this->tenant->run(fn () => expect(TenantLanguage::query()->where('language_code', 'fr')->value('is_enabled'))->toBeTrue()
        ->and(StoreCategory::query()->find($ids['category'])->getTranslation('name', 'fr', false))->toBe('[fr] Shoes')
        ->and(StoreTranslationItem::query()->where('status', TranslationItemStatus::Failed->value)->exists())->toBeFalse());
});

it('fails fast without calling a provider when the paid provider has no API key', function (): void {
    app(SettingsRepositoryContract::class)->forget(IntegrationSection::Ai->value, ['openai_api_key']);
    config(['ai.providers.openai.api_key' => null]);
    fakePaidProvider($this);
    seedStoreContent($this);

    $run = queueFrenchRun($this)->fresh();

    expect($run->status)->toBe(TranslationRunStatus::Failed)
        ->and($run->last_error)->toBe(__('ai::enums.ai_error_type.not_configured'))
        ->and($this->calls)->toBe(0);
});

it('rejects translations that drop placeholders after one retry', function (): void {
    Http::fake(['https://paid-ai.test/v1/*' => function (HttpRequest $request) {
        $texts = json_decode($request['messages'][1]['content'], true)['texts'];

        return Http::response(['choices' => [['message' => ['content' => json_encode(['translations' => array_map(static fn (string $text): string => str_replace('{{ size }}', 'taille', '[fr] '.$text), $texts)])]]]]);
    }]);
    $ids = seedStoreContent($this);

    $run = queueFrenchRun($this)->fresh();

    expect($run->status)->toBe(TranslationRunStatus::Failed)->and($run->failed_items)->toBe(1);
    $this->tenant->run(fn () => expect(StoreProduct::query()->find($ids['product'])->getTranslation('summary', 'fr', false))->toBe('')
        ->and(StoreTranslationItem::query()->where('section', 'products')->value('status'))->toBe(TranslationItemStatus::Failed));
});

it('serves the live run status to staff who can see the languages page only', function (): void {
    $run = StoreTranslationRun::withoutTenantScope()->create([
        'tenant_id' => $this->tenant->id, 'language_id' => $this->french->id, 'language_code' => 'fr',
        'status' => TranslationRunStatus::Translating, 'progress' => 40, 'total_items' => 10, 'done_items' => 4, 'queued_at' => now(),
    ]);
    $url = $this->tenantUrl($this->tenant, 'admin/settings/languages/runs');

    $this->getJson($url)->assertUnauthorized();

    $none = $this->tenant->run(function () {
        $staff = VendorStaff::factory()->create();
        $staff->givePermissionTo('dashboard.view');

        return $staff;
    });
    $this->actingAsVendor($none, $this->tenant)->getJson($url)->assertForbidden();

    $this->actingAsVendor($this->owner, $this->tenant)->getJson($url)
        ->assertOk()
        ->assertJsonPath('data.active', true)
        ->assertJsonPath('data.runs.fr.status', 'translating')
        ->assertJsonPath('data.runs.fr.progress', 40)
        ->assertJson(fn ($json) => $json->where('data.runs.fr.html', fn (string $html): bool => str_contains($html, 'data-progress="40"'))->etc());

    $this->get($this->tenantUrl($this->tenant, 'admin/settings/languages'))
        ->assertOk()
        ->assertSee('data-translation-live', false)
        ->assertSee('data-run-slot="fr"', false);

    expect($run->fresh()->status)->toBe(TranslationRunStatus::Translating);
});

it('weekly delta re-translates only new / changed items and never switches a language back on', function (): void {
    fakePaidProvider($this);
    $ids = seedStoreContent($this);
    queueFrenchRun($this);
    $this->sent = [];

    // Nothing changed: the delta run sends nothing.
    $this->artisan('ai:translate-deltas')->assertSuccessful();
    $delta = StoreTranslationRun::withoutTenantScope()->where('tenant_id', $this->tenant->id)->latest('id')->first();
    expect($delta->trigger)->toBe(TranslationRunTrigger::Delta)
        ->and($delta->status)->toBe(TranslationRunStatus::Completed)
        ->and($this->sent)->toBe([]);

    // A changed English source + a new category are the only texts sent.
    $this->tenant->run(function () use ($ids): void {
        StoreCategory::query()->find($ids['category'])->setTranslation('name', 'en', 'Sneakers')->save();
        StoreCategory::factory()->create(['name' => ['en' => 'Hats'], 'description' => null, 'meta_keywords' => null, 'meta_description' => null]);
    });
    app(DeltaTranslationService::class)->queueDue();

    expect($this->sent)->toContain('Sneakers')->toContain('Hats')->not->toContain('Summer sale')->not->toContain('Welcome');
    $this->tenant->run(fn () => expect(StoreCategory::query()->find($ids['category'])->getTranslation('name', 'fr', false))->toBe('[fr] Sneakers'));
    expect(AiUsageLog::withoutTenantScope()->where('feature', 'delta_translation')->where('tenant_id', $this->tenant->id)->exists())->toBeTrue();
    Notification::assertSentToTimes($this->owner, StoreTranslationRunNotification::class, 1);

    // A language the vendor switched off gets no delta run.
    $this->tenant->run(fn () => TenantLanguage::query()->where('language_code', 'fr')->update(['is_enabled' => false]));
    $before = StoreTranslationRun::withoutTenantScope()->count();
    expect(app(DeltaTranslationService::class)->queueDue())->toBe([])
        ->and(StoreTranslationRun::withoutTenantScope()->count())->toBe($before);
    $this->tenant->run(fn () => expect(TenantLanguage::query()->where('language_code', 'fr')->value('is_enabled'))->toBeFalse());
});

it('logs the paid run tokens in the AI usage log', function (): void {
    fakePaidProvider($this);
    seedStoreContent($this);
    $run = queueFrenchRun($this)->fresh();

    $logs = AiUsageLog::withoutTenantScope()->where('subject', 'run:'.$run->id)->get();
    expect($logs)->not->toBeEmpty()
        ->and($logs->first()->provider)->toBe('openai')
        ->and($logs->first()->model)->toBe('gpt-db-model')
        ->and($logs->sum('prompt_tokens'))->toBe($run->prompt_tokens);
});
