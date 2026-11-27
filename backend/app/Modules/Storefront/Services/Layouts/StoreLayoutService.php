<?php

namespace App\Modules\Storefront\Services\Layouts;

use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Catalog\Support\StoreCategoryRef;
use App\Modules\Localization\Services\StoreCountries;
use App\Modules\Notifications\Support\EmailHtmlSanitizer;
use App\Modules\Storefront\DTOs\Layouts\LayoutTargetData;
use App\Modules\Storefront\DTOs\ResolvedThemeData;
use App\Modules\Storefront\Enums\PageSectionType;
use App\Modules\Storefront\Enums\SectionProductSource;
use App\Modules\Storefront\Enums\StoreLayoutScope;
use App\Modules\Storefront\Enums\StorePageType;
use App\Modules\Storefront\Models\StoreLayout;
use App\Modules\Storefront\Models\StoreLayoutVersion;
use App\Modules\Storefront\Models\StorePage;
use App\Modules\Storefront\Models\Theme;
use App\Modules\Storefront\Repositories\Contracts\StoreLayoutRepositoryContract;
use App\Modules\Storefront\Repositories\Contracts\StorePageRepositoryContract;
use App\Modules\Storefront\Services\StoreThemeService;
use App\Modules\Storefront\Services\Themes\HomeLayoutService;
use App\Modules\Storefront\Support\ThemeSectionLibrary;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Page builder storage (VEN-032, decision #117 b): versioned JSON layouts per store (tenant
 * DB) for the home (store default + selling countries that render another theme) and for
 * store pages. The builder edits a draft (starts from the theme's admin default,
 * HomeLayoutService::editable); publishing copies the draft into `published` and keeps a
 * numbered version; restore copies a version back into the draft; reset = the theme
 * default. Items: {key, type?, enabled, settings, countries}; every submission is normalised
 * against the section's settings schema (ThemeSectionLibrary::schema / pageSchema).
 *
 * The storefront home, the Store API `home` and custom Twig themes read the PUBLISHED home
 * layout of the shopping country (country slot → store default slot), only while it was built
 * for the theme that country renders; otherwise the theme default applies.
 */
class StoreLayoutService extends BaseService
{
    public const TARGET_PATTERN = 'home-default|home-[0-9]+|page-[0-9]+';

    public const VERSIONS_KEPT = 20;

    public const MAX_PAGE_SECTIONS = 30;

    public const MAX_PICKS = 24;

    /** @var array<string, StoreLayout|null> */
    private array $slots = [];

    public function __construct(
        private readonly StoreLayoutRepositoryContract $layouts,
        private readonly StorePageRepositoryContract $pages,
        private readonly StoreThemeService $themes,
        private readonly StoreCountries $countries,
        private readonly HomeLayoutService $homeLayouts,
        private readonly CatalogLocales $locales,
    ) {}

    /**
     * Home scopes the builder offers: the store default and every selling country that
     * renders a different theme (decision #120 per-country themes). id => label.
     *
     * @return array<string, string>
     */
    public function homeScopes(): array
    {
        $default = $this->themes->resolveTheme(null);
        $scopes = ['default' => (string) __('content::page_builder.scope_default')];

        foreach ($this->countries->available() as $country) {
            $theme = $this->themes->resolveTheme((int) $country->id);

            if ($theme !== null && $theme->id !== $default?->id) {
                $scopes[(string) $country->id] = (string) __('content::page_builder.scope_country', ['country' => $country->name, 'theme' => $theme->name]);
            }
        }

        return $scopes;
    }

    /**
     * Selling countries (id => name) for the per-section "show in" picker.
     *
     * @return array<int, string>
     */
    public function sellingCountries(): array
    {
        return $this->countries->available()->mapWithKeys(fn ($country): array => [(int) $country->id => (string) $country->name])->all();
    }

    /**
     * Store languages (code => {name, dir}).
     *
     * @return array<string, array<string, mixed>>
     */
    public function locales(): array
    {
        return $this->locales->all();
    }

    public function homeTarget(?string $scope): ?LayoutTargetData
    {
        $countryId = $scope !== null && $scope !== 'default' && array_key_exists($scope, $this->homeScopes()) ? (int) $scope : null;
        $theme = $this->themes->resolveTheme($countryId);

        if ($theme === null) {
            return null;
        }

        return new LayoutTargetData(StoreLayoutScope::Home, StoreLayout::homeSlot($countryId), 'home-'.($countryId ?? 'default'), $countryId, $theme);
    }

    public function pageTarget(int $pageId): ?LayoutTargetData
    {
        $page = $this->pages->find($pageId);

        return $page instanceof StorePage ? new LayoutTargetData(StoreLayoutScope::Page, StoreLayout::pageSlot($page->id), 'page-'.$page->id, page: $page) : null;
    }

    /**
     * Route target ("home-default", "home-12", "page-3") → target; 404 when unknown.
     */
    public function target(string $key): LayoutTargetData
    {
        $target = match (true) {
            str_starts_with($key, 'page-') => $this->pageTarget((int) substr($key, 5)),
            $key === 'home-default' => $this->homeTarget('default'),
            default => array_key_exists(substr($key, 5), $this->homeScopes()) ? $this->homeTarget(substr($key, 5)) : null,
        };

        abort_if($target === null, 404);

        return $target;
    }

    public function layout(LayoutTargetData $target): ?StoreLayout
    {
        return $this->slots[$target->slot] ??= $this->layouts->findBySlot($target->slot);
    }

    /**
     * The layout was built for another theme than the home scope renders now.
     */
    public function isStale(LayoutTargetData $target): bool
    {
        $layout = $this->layout($target);

        return $target->isHome() && $layout !== null && ($layout->draft !== null || $layout->published !== null) && $layout->theme_id !== $target->theme?->id;
    }

    /**
     * Items the builder shows: the draft, else the published layout, else the theme default.
     *
     * @return list<array<string, mixed>>
     */
    public function workingItems(LayoutTargetData $target): array
    {
        $layout = $this->layout($target);

        if ($layout !== null && ! $this->isStale($target)) {
            $items = $layout->draft ?? $layout->published;

            if ($items !== null) {
                return $items;
            }
        }

        return $this->defaultItems($target);
    }

    /**
     * Builder sections: items merged with the library (home) / page types, with label + schema.
     *
     * @return list<array<string, mixed>>
     */
    public function editorSections(LayoutTargetData $target): array
    {
        $items = $this->workingItems($target);

        if ($target->isHome()) {
            $theme = $target->theme;
            assert($theme instanceof Theme);

            return array_map(static fn (array $section): array => $section + [
                'label' => ThemeSectionLibrary::label($section),
                'style_label' => ThemeSectionLibrary::styleLabel($section),
                'schema' => ThemeSectionLibrary::schema($theme->family, $section),
            ], $this->homeLayouts->arrangeItems($theme->family, (int) $theme->variant, $items));
        }

        return array_values(array_filter(array_map(static function (array $item): ?array {
            $type = PageSectionType::tryFrom((string) ($item['type'] ?? ''));

            return $type === null ? null : $item + ['label' => $type->label(), 'icon' => $type->icon(), 'schema' => ThemeSectionLibrary::pageSchema($type)];
        }, $items)));
    }

    /**
     * @param  list<array<string, mixed>>  $input
     */
    public function saveDraft(LayoutTargetData $target, array $input): StoreLayout
    {
        return $this->write($target, ['draft' => $this->normalise($target, $input), 'draft_updated_at' => now()]);
    }

    /**
     * Throw the draft away (back to what is published).
     */
    public function discard(LayoutTargetData $target): void
    {
        if ($this->layout($target) !== null) {
            $this->write($target, ['draft' => null, 'draft_updated_at' => null]);
        }
    }

    /**
     * Reset to the theme default (home) / an empty page, as a draft to review and publish.
     */
    public function reset(LayoutTargetData $target): StoreLayout
    {
        return $this->write($target, ['draft' => $this->defaultItems($target), 'draft_updated_at' => now()]);
    }

    public function restore(LayoutTargetData $target, int $versionId): StoreLayoutVersion
    {
        $layout = $this->layout($target);
        $version = $layout !== null ? $this->layouts->findVersion($layout, $versionId) : null;
        abort_if($version === null, 404);

        $this->write($target, ['draft' => $this->normalise($target, $version->sections), 'draft_updated_at' => now()]);

        return $version;
    }

    public function publish(LayoutTargetData $target, ?int $staffId, ?string $staffName): StoreLayoutVersion
    {
        return $this->transaction(function () use ($target, $staffId, $staffName): StoreLayoutVersion {
            $sections = $this->normalise($target, $this->workingItems($target));
            $layout = $this->write($target, ['published' => $sections, 'draft' => null, 'draft_updated_at' => null, 'published_at' => now()]);
            $version = $this->layouts->addVersion($layout, $sections, $staffId, $staffName);
            $this->layouts->update($layout, ['published_version_id' => $version->id]);
            $this->layouts->pruneVersions($layout, self::VERSIONS_KEPT);

            return $version;
        });
    }

    /**
     * @return Collection<int, StoreLayoutVersion>
     */
    public function versions(LayoutTargetData $target): Collection
    {
        $layout = $this->layout($target);

        return $layout !== null ? $this->layouts->versions($layout, self::VERSIONS_KEPT) : new Collection;
    }

    /**
     * Published (or previewed draft) home items of a storefront request, or null = theme default.
     *
     * @return list<array<string, mixed>>|null
     */
    public function homeItems(ResolvedThemeData $theme, ?int $countryId, ?StoreLayout $preview = null): ?array
    {
        if (! tenancy()->initialized) {
            return null;
        }

        if ($preview !== null && $preview->scope === StoreLayoutScope::Home) {
            return $preview->draft ?? $preview->published ?? [];
        }

        foreach (array_unique([StoreLayout::homeSlot($countryId), StoreLayout::homeSlot(null)]) as $slot) {
            $layout = $this->slots[$slot] ??= $this->layouts->findBySlot($slot);

            if ($layout !== null && $layout->published !== null && $theme->id !== null && $layout->theme_id === $theme->id) {
                return $layout->published;
            }
        }

        return null;
    }

    /**
     * Enabled, country-visible page sections of a store page (published, or the draft in a
     * signed preview), raw settings.
     *
     * @return list<array<string, mixed>>
     */
    public function pageItems(StorePage $page, ?int $countryId, ?StoreLayout $preview = null): array
    {
        if (! tenancy()->initialized) {
            return [];
        }

        $slot = StoreLayout::pageSlot($page->id);
        $layout = $preview !== null && $preview->slot === $slot ? $preview : ($this->slots[$slot] ??= $this->layouts->findBySlot($slot));
        $items = $layout === null ? [] : ($layout === $preview ? ($layout->draft ?? $layout->published ?? []) : ($layout->published ?? []));

        return array_values(array_filter($items, static fn (array $item): bool => ($item['enabled'] ?? true) && PageSectionType::tryFrom((string) ($item['type'] ?? '')) !== null
            && (($item['countries'] ?? []) === [] || ($countryId !== null && in_array($countryId, $item['countries'], true)))));
    }

    /**
     * Plain, rendered page sections of a store page (custom Twig themes, decision #121).
     *
     * @return list<array<string, mixed>>
     */
    public function pageSections(StorePage $page): array
    {
        return app(PageSectionRenderer::class)->plain($page);
    }

    /**
     * Custom pages the builder can edit (system pages keep their text layout).
     *
     * @return Collection<int, StorePage>
     */
    public function customPages(): Collection
    {
        return $this->pages->query()->where('type', StorePageType::Custom->value)->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function defaultItems(LayoutTargetData $target): array
    {
        if (! $target->isHome()) {
            return [];
        }

        $theme = $target->theme;
        assert($theme instanceof Theme);

        return array_map(static fn (array $section): array => ['key' => $section['key'], 'enabled' => (bool) $section['enabled'], 'settings' => [], 'countries' => []], $this->homeLayouts->editable($theme));
    }

    /**
     * Normalise a submission against the section schemas.
     *
     * @param  array<int|string, mixed>  $input
     * @return list<array<string, mixed>>
     */
    public function normalise(LayoutTargetData $target, array $input): array
    {
        $countries = array_keys($this->sellingCountries());
        $locales = array_keys($this->locales());
        $items = [];
        $seen = [];

        if ($target->isHome()) {
            $theme = $target->theme;
            assert($theme instanceof Theme);
            $library = [];

            foreach (ThemeSectionLibrary::sections($theme->family, (int) $theme->variant) as $section) {
                $library[$section['key']] = $section;
            }

            foreach ($input as $item) {
                $key = is_array($item) ? ($item['key'] ?? null) : null;

                if (! is_string($key) || ! isset($library[$key]) || isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $items[] = [
                    'key' => $key,
                    'enabled' => filter_var($item['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'settings' => $this->settings(ThemeSectionLibrary::schema($theme->family, $library[$key]), is_array($item['settings'] ?? null) ? $item['settings'] : [], $locales),
                    'countries' => self::countries($item['countries'] ?? [], $countries),
                ];
            }

            foreach ($library as $key => $section) {
                if (! isset($seen[$key])) {
                    $items[] = ['key' => $key, 'enabled' => false, 'settings' => [], 'countries' => []];
                }
            }

            return $items;
        }

        foreach ($input as $item) {
            $type = is_array($item) ? PageSectionType::tryFrom((string) ($item['type'] ?? '')) : null;

            if ($type === null || count($items) >= self::MAX_PAGE_SECTIONS) {
                continue;
            }

            $key = is_string($item['key'] ?? null) && preg_match('/^[a-z0-9]{4,16}$/', $item['key']) === 1 && ! isset($seen[$item['key']]) ? $item['key'] : Str::lower(Str::random(8));
            $seen[$key] = true;
            $items[] = [
                'key' => $key,
                'type' => $type->value,
                'enabled' => filter_var($item['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'settings' => $this->settings(ThemeSectionLibrary::pageSchema($type), is_array($item['settings'] ?? null) ? $item['settings'] : [], $locales),
                'countries' => self::countries($item['countries'] ?? [], $countries),
            ];
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $schema
     * @param  array<string, mixed>  $input
     * @param  list<string>  $locales
     * @return array<string, mixed>
     */
    private function settings(array $schema, array $input, array $locales): array
    {
        $settings = [];

        foreach ($schema as $field) {
            $name = (string) $field['name'];
            $value = $input[$name] ?? null;
            $max = (int) ($field['max'] ?? 500);

            switch ($field['type']) {
                case 'text':
                case 'textarea':
                case 'richtext':
                    $texts = [];

                    foreach ($locales as $locale) {
                        $text = is_array($value) && is_string($value[$locale] ?? null) ? trim($value[$locale]) : '';
                        $text = $field['type'] === 'richtext' ? (trim(strip_tags($text)) === '' ? '' : EmailHtmlSanitizer::clean($text)) : trim(strip_tags($text));

                        if ($text !== '') {
                            $texts[$locale] = mb_substr($text, 0, $max);
                        }
                    }

                    if ($texts !== []) {
                        $settings[$name] = $texts;
                    }
                    break;
                case 'number':
                    if (is_numeric($value)) {
                        $settings[$name] = max((int) $field['min'], min((int) $field['max'], (int) $value));
                    }
                    break;
                case 'select':
                    if (is_string($value) && in_array($value, $field['options'], true) && $value !== ($field['default'] ?? null)) {
                        $settings[$name] = $value;
                    }
                    break;
                case 'source':
                    $settings += self::source($input, ! empty($field['no_default']));
                    break;
                case 'banners':
                    $settings[$name] = array_values(array_unique(array_filter(array_map('strval', is_array($value) ? $value : []), static fn (string $key): bool => preg_match('/^(central|vendor):[1-9]\d{0,18}$/', $key) === 1)));
                    $settings[$name] = array_slice($settings[$name], 0, 10);

                    if ($settings[$name] === []) {
                        unset($settings[$name]);
                    }
                    break;
                case 'categories':
                    $refs = array_values(array_unique(array_filter(array_map(static fn (mixed $key): ?string => StoreCategoryRef::tryParse($key)?->key(), is_array($value) ? $value : []))));

                    if ($refs !== []) {
                        $settings[$name] = array_slice($refs, 0, self::MAX_PICKS);
                    }
                    break;
                case 'faq':
                    $faq = [];

                    foreach (is_array($value) ? $value : [] as $row) {
                        $question = [];
                        $answer = [];

                        foreach ($locales as $locale) {
                            $q = is_array($row) && is_array($row['q'] ?? null) && is_string($row['q'][$locale] ?? null) ? trim(strip_tags($row['q'][$locale])) : '';
                            $a = is_array($row) && is_array($row['a'] ?? null) && is_string($row['a'][$locale] ?? null) ? trim(strip_tags($row['a'][$locale])) : '';

                            if ($q !== '' && $a !== '') {
                                $question[$locale] = mb_substr($q, 0, 300);
                                $answer[$locale] = mb_substr($a, 0, 2000);
                            }
                        }

                        if ($question !== [] && count($faq) < $max) {
                            $faq[] = ['q' => $question, 'a' => $answer];
                        }
                    }

                    if ($faq !== []) {
                        $settings[$name] = $faq;
                    }
                    break;
                case 'image':
                    if (is_string($value) && preg_match('#^'.preg_quote(PageBuilderImageStore::DIRECTORY, '#').'/[A-Za-z0-9._-]+$#', $value) === 1 && ! str_contains($value, '..')) {
                        $settings[$name] = $value;
                    }
                    break;
                case 'url':
                    if (is_string($value) && ($url = trim($value)) !== '' && (preg_match('#^https?://#i', $url) === 1 || preg_match('#^/(?!/)#', $url) === 1)) {
                        $settings[$name] = mb_substr($url, 0, $max);
                    }
                    break;
            }
        }

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private static function source(array $input, bool $noDefault): array
    {
        $source = SectionProductSource::tryFrom((string) ($input['source'] ?? '')) ?? ($noDefault ? SectionProductSource::NewIn : SectionProductSource::Default);

        if ($noDefault && $source === SectionProductSource::Default) {
            $source = SectionProductSource::NewIn;
        }

        $result = $source === SectionProductSource::Default ? [] : ['source' => $source->value];

        if ($source === SectionProductSource::Category) {
            $ref = StoreCategoryRef::tryParse($input['category'] ?? null);
            $ref !== null ? $result['category'] = $ref->key() : $result = $noDefault ? ['source' => SectionProductSource::NewIn->value] : [];
        }

        if ($source === SectionProductSource::Badge) {
            $badge = filter_var($input['badge'] ?? null, FILTER_VALIDATE_INT);
            is_int($badge) && $badge > 0 ? $result['badge'] = $badge : $result = $noDefault ? ['source' => SectionProductSource::NewIn->value] : [];
        }

        if ($source === SectionProductSource::Manual) {
            $ids = array_values(array_unique(array_filter(array_map('intval', is_array($input['products'] ?? null) ? $input['products'] : []), static fn (int $id): bool => $id > 0)));
            $ids !== [] ? $result['products'] = array_slice($ids, 0, self::MAX_PICKS) : $result = $noDefault ? ['source' => SectionProductSource::NewIn->value] : [];
        }

        return $result;
    }

    /**
     * @param  list<int>  $allowed
     * @return list<int>
     */
    private static function countries(mixed $value, array $allowed): array
    {
        $ids = array_values(array_unique(array_map('intval', is_array($value) ? $value : [])));

        return array_values(array_intersect($ids, $allowed));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function write(LayoutTargetData $target, array $attributes): StoreLayout
    {
        $layout = $this->layout($target);
        $attributes += ['theme_id' => $target->theme?->id];

        if ($target->isHome() && $layout !== null && $layout->theme_id !== $target->theme?->id) {
            // Built for another theme: the old published layout no longer applies.
            $attributes += ['published' => null];
        }

        $layout = $layout === null
            ? $this->layouts->create($attributes + ['slot' => $target->slot, 'scope' => $target->scope->value, 'country_id' => $target->countryId, 'page_id' => $target->page?->id])
            : $this->layouts->update($layout, $attributes);

        return $this->slots[$target->slot] = $layout->refresh();
    }
}
