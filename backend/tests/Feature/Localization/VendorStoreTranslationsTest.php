<?php

use App\Modules\Ai\Contracts\TranslationClient;
use App\Modules\Ai\Enums\AiErrorType;
use App\Modules\Ai\Enums\TranslationProvider;
use App\Modules\Ai\Exceptions\AiRequestException;
use App\Modules\Catalog\Models\StoreCategory;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Localization\Enums\StaticTextSource;
use App\Modules\Localization\Enums\StoreContentType;
use App\Modules\Localization\Jobs\TranslateMissingStaticTexts;
use App\Modules\Localization\Models\ContentTranslationLock;
use App\Modules\Localization\Models\Language;
use App\Modules\Localization\Models\TenantStaticText;
use App\Modules\Localization\Services\LanguageService;
use App\Modules\Localization\Services\StaticTexts\StaticTextCatalog;
use App\Modules\Localization\Services\TenantLanguageService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/*
| VEN-075 Vendor › Settings › Store translations: permissions, static texts per store
| language (tabs, edit = manual + locked, reset, "Translate" suggestion via the custom
| provider, "Translate missing with AI" queued job), store content fields (edit locks the
| field, unlock), validation and isolation.
*/

function fakeTranslationClient(bool $fail = false): void
{
    app()->instance(TranslationClient::class, new class($fail) implements TranslationClient
    {
        public function __construct(private readonly bool $fail) {}

        public function provider(): TranslationProvider
        {
            return TranslationProvider::FreeLlmApi;
        }

        public function translate(array $texts, string $from, string $to, array $glossary = []): array
        {
            if ($this->fail) {
                throw new AiRequestException(AiErrorType::cases()[0]);
            }

            return array_map(static fn (string $text): string => '['.$to.'] '.$text, $texts);
        }
    });
}

beforeEach(function (): void {
    $this->tenant = $this->createTenant('trn-'.Str::lower(Str::random(6)));
    $this->tenant->run(fn () => app(TenantLanguageService::class)->enableCoreLanguages());
    Language::query()->where('code', 'fr')->first() ?? Language::factory()->create(['code' => 'fr', 'name' => ['en' => 'French', 'ar' => 'الفرنسية'], 'native_name' => 'Français', 'is_active' => true, 'price_minor' => 4900]);
    app(LanguageService::class)->activateForTenant($this->tenant, 'fr');
    $this->owner = $this->createVendorOwner($this->tenant, ['email' => 'owner-'.Str::lower(Str::random(6)).'@megz.test']);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/settings/translations'.$path);
    $this->as = fn () => $this->actingAsVendor($this->owner, $this->tenant);
    $this->key = app(StaticTextCatalog::class)->keys()[0];
});

it('guards the pages and writes by the settings.translations permissions', function (): void {
    $this->get(($this->url)())->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    [$none, $viewer] = $this->tenant->run(function (): array {
        $none = VendorStaff::factory()->create();
        $none->givePermissionTo('dashboard.view');
        $viewer = VendorStaff::factory()->create();
        $viewer->givePermissionTo(['dashboard.view', 'settings.translations.view']);

        return [$none, $viewer];
    });

    $this->actingAsVendor($none, $this->tenant);
    $this->get(($this->url)())->assertForbidden();
    $this->get(($this->url)('/content'))->assertForbidden();

    $this->actingAsVendor($viewer, $this->tenant);
    $this->get(($this->url)())->assertOk()->assertSee(__('localization::vendor_translations.title'))->assertDontSee('translation-edit-form', false);
    $this->get(($this->url)('/content'))->assertOk();
    $this->putJson(($this->url)('/static'), ['key' => $this->key, 'locale' => 'fr', 'value' => 'x'])->assertForbidden();
    $this->postJson(($this->url)('/static/translate-missing'), ['locale' => 'fr'])->assertForbidden();
});

it('lists, edits, suggests and resets static texts of a store language', function (): void {
    fakeTranslationClient();
    ($this->as)();

    $this->get(($this->url)('?locale=fr'))->assertOk()->assertSee(__('localization::vendor_translations.tabs.missing', ['language' => 'FR']))->assertSee('translation-edit-form', false);
    $rows = $this->getJson(($this->url)('/data?locale=fr&tab=missing&draw=1&start=0&length=10'))->assertOk()->json();
    expect($rows['recordsTotal'])->toBeGreaterThan(0);

    $this->putJson(($this->url)('/static'), ['key' => $this->key, 'locale' => 'de', 'value' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('locale');
    $this->putJson(($this->url)('/static'), ['key' => $this->key, 'locale' => 'fr'])->assertUnprocessable()->assertJsonValidationErrors('value');
    $this->putJson(($this->url)('/static'), ['key' => 'no.such.key', 'locale' => 'fr', 'value' => 'x'])->assertUnprocessable();

    $this->putJson(($this->url)('/static'), ['key' => $this->key, 'locale' => 'fr', 'value' => 'Bonjour'])->assertOk();
    $row = $this->tenant->run(fn () => TenantStaticText::query()->where('key', $this->key)->where('locale', 'fr')->sole());
    expect($row->value)->toBe('Bonjour')->and($row->source)->toBe(StaticTextSource::Manual)->and($row->is_locked)->toBeTrue();

    $this->postJson(($this->url)('/static/suggest'), ['key' => $this->key, 'locale' => 'fr'])->assertOk()->assertJsonPath('data.text', fn (string $text): bool => str_starts_with($text, '[fr] '));

    $this->postJson(($this->url)('/static/reset').'?key='.urlencode($this->key).'&locale=fr')->assertOk();
    expect($this->tenant->run(fn () => TenantStaticText::query()->where('key', $this->key)->where('locale', 'fr')->exists()))->toBeFalse();

    fakeTranslationClient(fail: true);
    $this->postJson(($this->url)('/static/suggest'), ['key' => $this->key, 'locale' => 'fr'])->assertUnprocessable()->assertJsonPath('message', __('localization::vendor_translations.errors.ai_unavailable'));
});

it('queues "translate missing" and fills only empty, unlocked texts', function (): void {
    Queue::fake();
    ($this->as)();
    $this->postJson(($this->url)('/static/translate-missing'), ['locale' => 'xx'])->assertUnprocessable();
    $this->postJson(($this->url)('/static/translate-missing'), ['locale' => 'fr'])->assertOk();
    Queue::assertPushed(TranslateMissingStaticTexts::class, fn ($job): bool => $job->tenantId === $this->tenant->id && $job->locale === 'fr');

    $this->putJson(($this->url)('/static'), ['key' => $this->key, 'locale' => 'fr', 'value' => 'Mon texte'])->assertOk();
    fakeTranslationClient();
    app()->call([new TranslateMissingStaticTexts($this->tenant->id, 'fr'), 'handle']);

    $rows = $this->tenant->run(fn () => TenantStaticText::query()->where('locale', 'fr')->get()->keyBy('key'));
    $other = app(StaticTextCatalog::class)->keys()[1];
    expect($rows[$this->key]->value)->toBe('Mon texte')
        ->and($rows[$other]->source)->toBe(StaticTextSource::Ai)
        ->and($rows[$other]->value)->toStartWith('[fr] ');
});

it('translates store content fields by hand and locks them from the AI run', function (): void {
    fakeTranslationClient();
    $category = $this->tenant->run(fn () => StoreCategory::factory()->create(['name' => ['en' => 'Perfumes', 'ar' => 'عطور']]));
    ($this->as)();

    $this->get(($this->url)('/content?locale=fr&type=category&field=name'))->assertOk()->assertSee(StoreContentType::Category->label());
    $data = $this->getJson(($this->url)('/content/data?locale=fr&type=category&field=name&draw=1&start=0&length=10'))->assertOk()->json('data');
    expect($data)->toHaveCount(1)->and($data[0]['english'])->toContain('Perfumes');

    $payload = ['type' => 'category', 'id' => $category->id, 'field' => 'name', 'locale' => 'fr'];
    $this->putJson(($this->url)('/content'), [...$payload, 'field' => 'slug', 'value' => 'x'])->assertUnprocessable();
    $this->putJson(($this->url)('/content'), [...$payload, 'type' => 'order', 'value' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('type');
    $this->putJson(($this->url)('/content'), [...$payload, 'id' => 999999, 'value' => 'x'])->assertUnprocessable();

    $this->postJson(($this->url)('/content/suggest'), $payload)->assertOk()->assertJsonPath('data.text', '[fr] Perfumes');
    $this->putJson(($this->url)('/content'), [...$payload, 'value' => 'Parfums'])->assertOk();

    $this->tenant->run(function () use ($category): void {
        expect($category->fresh()->getTranslation('name', 'fr', false))->toBe('Parfums')
            ->and(ContentTranslationLock::query()->where(['content_type' => 'category', 'content_id' => $category->id, 'field' => 'name', 'locale' => 'fr'])->exists())->toBeTrue();
    });

    $this->postJson(($this->url)('/content/unlock').'?'.http_build_query($payload))->assertOk();
    expect($this->tenant->run(fn () => ContentTranslationLock::query()->count()))->toBe(0);

    // Another store cannot reach this record.
    $beta = $this->createTenant('trn-'.Str::lower(Str::random(6)));
    $beta->run(fn () => app(TenantLanguageService::class)->enableCoreLanguages());
    $betaOwner = $this->createVendorOwner($beta, ['email' => 'beta-'.Str::lower(Str::random(6)).'@megz.test']);
    $this->actingAsVendor($betaOwner, $beta);
    $this->putJson($this->tenantUrl($beta, 'admin/settings/translations/content'), [...$payload, 'locale' => 'ar', 'value' => 'x'])->assertUnprocessable();
    expect($this->tenant->run(fn () => $category->fresh()->getTranslation('name', 'ar', false)))->toBe('عطور');
});
