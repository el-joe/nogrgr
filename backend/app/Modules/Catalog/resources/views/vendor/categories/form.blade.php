{{--
    Store category create / edit (VEN-017b, Tenant Panel Layout 12:17672, mobile 12:22624):
    Basics (localized name / meta keywords / meta description / description, parent, order
    number, countries with search, slug, active, featured) and the primary image. Saved
    through the ajax form; success returns to the categories page.
    Data: $category (StoreCategory, new or existing), $parentOptions, $countries.
--}}
@php
    $editing = $category->exists;
    $locales = \App\Shared\Support\Locale::enabled();
    $fallback = (string) config('app.fallback_locale', 'en');
    $translation = fn (string $field, string $locale) => $editing ? $category->getTranslation($field, $locale, false) : null;
    $selectedCountries = $category->country_ids ?? [];
    $allCountries = ! $editing || $category->country_ids === null;
    $title = $editing ? __('catalog::taxonomy.categories.edit_title') : __('catalog::taxonomy.categories.create_title');
@endphp
<x-vendor::layout :title="$title" page="vendor-catalog">
    <form id="store-category-form" method="POST" action="{{ $editing ? vendor_route('catalog.categories.update', ['own' => $category->id]) : vendor_route('catalog.categories.store') }}" enctype="multipart/form-data" class="grid gap-6" data-ajax data-category-form novalidate>
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <header class="flex flex-wrap items-start justify-between gap-4" data-reveal="fade-up">
            <div class="grid gap-1">
                <h1 class="text-[26px] font-bold leading-[1.5] text-ink sm:text-[32px]">{{ $title }}</h1>
                <p class="text-sm text-ink-muted">{{ __('catalog::taxonomy.categories.form_subtitle') }}</p>
            </div>
            <div class="flex w-full flex-wrap gap-3 sm:w-auto">
                <a href="{{ vendor_route('catalog.categories.index') }}" class="vnd-cta vnd-cta-outline flex-1 sm:w-[183px] sm:flex-none"><x-ui::icon name="chevron-left" :size="20" data-flip />{{ __('catalog::taxonomy.categories.back') }}</a>
                <button type="submit" class="vnd-cta vnd-cta-primary flex-1 sm:w-[183px] sm:flex-none">{{ __('catalog::taxonomy.categories.save') }}</button>
            </div>
        </header>

        <section class="grid gap-5 rounded-[24px] bg-surface p-4 sm:p-6">
            <div>
                <h2 class="text-lg font-medium text-ink">{{ __('catalog::taxonomy.categories.basics') }}</h2>
                <p class="text-xs text-ink-muted">{{ __('catalog::taxonomy.categories.basics_hint') }}</p>
            </div>

            <x-ui::locale-tabs id="category-i18n" :active="$fallback">
                @foreach ($locales as $code => $language)
                    <x-ui::locale-pane parent="category-i18n" :locale="$code" :active="$code === $fallback">
                        <div class="grid gap-4 md:grid-cols-3">
                            <x-ui::input :name="'name['.$code.']'" :label="__('catalog::taxonomy.fields.name')" :value="$translation('name', $code)" :required="$code === $fallback" :dir="$language['dir'] ?? 'ltr'" maxlength="120" />
                            <x-ui::input :name="'meta_keywords['.$code.']'" :label="__('catalog::taxonomy.fields.meta_keywords')" :value="$translation('meta_keywords', $code)" :dir="$language['dir'] ?? 'ltr'" maxlength="255" />
                            <x-ui::input :name="'meta_description['.$code.']'" :label="__('catalog::taxonomy.fields.meta_description')" :value="$translation('meta_description', $code)" :dir="$language['dir'] ?? 'ltr'" maxlength="500" />
                        </div>
                        <x-ui::textarea :name="'description['.$code.']'" :label="__('catalog::taxonomy.fields.description')" :value="$translation('description', $code)" :rows="4" class="mt-4" />
                    </x-ui::locale-pane>
                @endforeach
            </x-ui::locale-tabs>

            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui::select name="parent_id" :label="__('catalog::taxonomy.fields.parent')" :options="$parentOptions" :selected="$category->parent_id" :placeholder="__('catalog::taxonomy.fields.root')" allow-clear />
                <x-ui::input name="sort_order" type="number" min="0" :label="__('catalog::taxonomy.fields.order')" :value="$editing ? $category->sort_order : null" dir="ltr" inputmode="numeric" />
                <x-ui::input name="slug" :label="__('catalog::taxonomy.fields.slug')" :value="$category->slug" :helper="__('catalog::taxonomy.fields.slug_hint')" dir="ltr" maxlength="180" />
            </div>

            <fieldset class="grid gap-3" data-country-picker>
                <legend class="mb-2 text-sm font-medium text-ink">{{ __('catalog::taxonomy.fields.countries') }}</legend>
                @if ($countries->isEmpty())
                    <p class="rounded-control bg-warning-bg p-3 text-sm text-warning">{{ __('catalog::vendor.products.no_countries') }}</p>
                    <input type="hidden" name="all_countries" value="1">
                @else
                    <x-ui::checkbox name="all_countries" :label="__('catalog::taxonomy.fields.all_countries')" :checked="$allCountries" data-countries-all />
                    <div class="ui-control min-h-10">
                        <x-ui::icon name="search" :size="18" class="text-ink-muted" />
                        <label for="category-country-search" class="sr-only">{{ __('catalog::taxonomy.fields.search_countries') }}</label>
                        <input id="category-country-search" type="search" class="h-9" placeholder="{{ __('catalog::taxonomy.fields.search_countries') }}" data-markets-search autocomplete="off">
                    </div>
                    <ul class="grid max-h-[180px] gap-1 overflow-y-auto rounded-card bg-subtle p-3 sm:grid-cols-2" data-markets-list>
                        @foreach ($countries as $country)
                            <li data-market-option data-name="{{ \Illuminate\Support\Str::lower($country->name.' '.$country->iso2) }}">
                                <x-ui::checkbox name="country_ids[]" :value="$country->id" :label="trim($country->flag.' '.$country->name)" :checked="in_array($country->id, $selectedCountries, true)" />
                            </li>
                        @endforeach
                        <li data-markets-none hidden class="py-2 text-sm text-ink-muted">{{ __('catalog::taxonomy.fields.no_country_match') }}</li>
                    </ul>
                    <p class="text-xs text-danger" data-error-for="country_ids"></p>
                @endif
            </fieldset>

            <div class="flex flex-wrap gap-x-10 gap-y-3">
                <x-ui::checkbox name="is_active" :label="__('catalog::taxonomy.fields.active')" :checked="$category->is_active ?? true" unchecked-value="0" />
                <x-ui::checkbox name="is_featured" :label="__('catalog::taxonomy.fields.featured')" :checked="(bool) $category->is_featured" unchecked-value="0" />
            </div>
        </section>

        <section class="grid gap-4 rounded-[24px] bg-surface p-4 sm:p-6">
            <div>
                <h2 class="text-lg font-medium text-ink">{{ __('catalog::taxonomy.categories.image_title') }}</h2>
                <p class="text-xs text-ink-muted">{{ __('catalog::taxonomy.fields.image_hint') }}</p>
            </div>
            <x-ui::file-upload name="image" accept="image/png,image/jpeg,image/webp" :hint="__('catalog::taxonomy.fields.image_formats')" />
            @if ($editing && $category->imageUrl())
                <div class="flex flex-wrap items-center gap-4">
                    <img src="{{ $category->imageUrl() }}" alt="{{ $category->name }}" class="size-16 rounded-control object-cover" loading="lazy">
                    <x-ui::checkbox name="remove_image" :label="__('catalog::taxonomy.fields.remove_image')" unchecked-value="0" />
                </div>
            @endif
        </section>
    </form>
</x-vendor::layout>
