<?php

use App\Modules\Localization\Enums\TenantLanguageSource;
use App\Modules\Localization\Exceptions\LockedRecordException;
use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Models\Language;
use App\Modules\Localization\Models\TenantLanguage;
use App\Modules\Localization\Services\LanguageService;
use App\Modules\Localization\Services\TenantLanguageService;

/*
| Store languages (decision #45): Arabic and English are switched on for every store;
| other active, priced languages are bought and switched on after the paid AI run.
*/

it('enables arabic and english for the current store, idempotently', function (): void {
    $tenant = $this->createTenant('langs');
    $this->initializeTenancy($tenant);

    $service = app(TenantLanguageService::class);
    $service->enableCoreLanguages();
    TenantLanguage::query()->where('language_code', 'ar')->update(['is_enabled' => false]);
    $languages = $service->enableCoreLanguages();

    expect($languages->pluck('language_code')->all())->toBe(['ar', 'en'])
        ->and($languages->every(fn (TenantLanguage $language): bool => $language->is_enabled && $language->source === TenantLanguageSource::Default))->toBeTrue()
        ->and($languages->where('is_default', true)->pluck('language_code')->all())->toBe(['en']);
});

it('keeps an existing store default language', function (): void {
    $tenant = $this->createTenant('langs2');
    $this->initializeTenancy($tenant);
    TenantLanguage::factory()->create(['language_code' => 'fr', 'is_default' => true]);

    $languages = app(TenantLanguageService::class)->enableCoreLanguages();

    expect($languages->where('is_default', true)->pluck('language_code')->all())->toBe(['fr']);
});

it('refuses to run without an initialized store', function (): void {
    app(TenantLanguageService::class)->enableCoreLanguages();
})->throws(LogicException::class);

it('lists the languages a store can buy', function (): void {
    $saudi = Country::factory()->create();
    $france = Country::factory()->create();
    Language::factory()->code('ar')->create(['price_minor' => 0]);
    Language::factory()->code('fr')->paid(4900)->create(['country_ids' => [$france->id]]);
    Language::factory()->code('ur')->paid(3900)->create();
    Language::factory()->code('tr')->paid(3900)->inactive()->create();
    Language::factory()->code('de')->create(['price_minor' => 0]);
    Language::factory()->code('es')->paid(2900)->create(['country_ids' => [$saudi->id]]);

    $tenant = $this->createTenant('buyer');
    $tenant->update(['selling_country_ids' => [$saudi->id]]);
    $tenant->run(fn () => TenantLanguage::factory()->create(['language_code' => 'es']));

    $codes = app(LanguageService::class)->availableToPurchase($tenant->refresh())->pluck('code')->all();

    expect($codes)->toEqualCanonicalizing(['ur']);
});

it('switches a bought language on for the store after the paid run, idempotently', function (): void {
    Language::factory()->code('fr')->paid(4900)->create();
    $tenant = $this->createTenant('buyer2');
    $service = app(LanguageService::class);

    $service->activateForTenant($tenant, 'fr');
    $language = $service->activateForTenant($tenant, 'fr');

    expect($language->language_code)->toBe('fr')
        ->and($language->is_enabled)->toBeTrue()
        ->and($language->source)->toBe(TenantLanguageSource::Purchased)
        ->and($tenant->run(fn () => TenantLanguage::query()->count()))->toBe(1);
});

it('never sells or re-activates arabic and english through a purchase', function (): void {
    Language::factory()->code('en')->create(['price_minor' => 0]);

    app(LanguageService::class)->activateForTenant($this->createTenant('buyer3'), 'en');
})->throws(LockedRecordException::class);
