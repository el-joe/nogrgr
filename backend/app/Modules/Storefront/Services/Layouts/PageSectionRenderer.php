<?php

namespace App\Modules\Storefront\Services\Layouts;

use App\Modules\Catalog\Enums\StoreCategorySource;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Support\StoreCategoryRef;
use App\Modules\Storefront\DTOs\BannerData;
use App\Modules\Storefront\Enums\PageSectionType;
use App\Modules\Storefront\Http\Resources\StoreApi\CatalogCards;
use App\Modules\Storefront\Models\StorePage;
use App\Modules\Storefront\Services\Catalog\CategoryCatalog;
use App\Modules\Storefront\Services\StorefrontContext;
use App\Modules\Storefront\Support\StoreOwnCategory;

/**
 * Store page sections ready to render (VEN-032 / VEN-033): texts in the shopper's language
 * (sections whose texts are all empty in it are skipped), products by source, picked
 * banners, categories, FAQ rows and the image URL. `render()` feeds the shared Blade page
 * (x-sf::page-section.*); `plain()` the custom Twig themes / API (arrays only).
 */
class PageSectionRenderer
{
    public function __construct(
        private readonly StoreLayoutService $layouts,
        private readonly StoreLayoutPreviewService $previews,
        private readonly SectionProductResolver $products,
        private readonly HomeSectionHydrator $hydrator,
        private readonly CategoryCatalog $categories,
        private readonly StorefrontContext $context,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function render(StorePage $page): array
    {
        $locale = app()->getLocale();
        $sections = [];

        foreach ($this->layouts->pageItems($page, $this->context->countryId(), $this->previews->current(request())) as $item) {
            $type = PageSectionType::from((string) $item['type']);
            $settings = (array) ($item['settings'] ?? []);
            $text = static fn (string $field): ?string => is_string($settings[$field][$locale] ?? null) && $settings[$field][$locale] !== '' ? $settings[$field][$locale] : null;
            $section = [
                'key' => (string) $item['key'],
                'type' => $type->value,
                'component' => $type->component(),
                'title' => $text('title'),
                'subtitle' => $text('subtitle'),
                'body' => $text('body'),
                'style' => (string) ($settings['style'] ?? 'contained'),
            ];

            $section += match ($type) {
                PageSectionType::RichText => ['ready' => $section['body'] !== null],
                PageSectionType::Banner => ['banners' => $banners = $this->hydrator->pick(null, (array) ($settings['banners'] ?? [])), 'ready' => $banners !== []],
                PageSectionType::ProductRail => ['cards' => $cards = $this->products->cards($settings + ['source' => 'new'], null) ?? [], 'ready' => $cards !== []],
                PageSectionType::CategoryGrid => ['categories' => $categories = $this->categoryList((array) ($settings['categories'] ?? []), (int) ($settings['limit'] ?? 8)), 'ready' => $categories !== []],
                PageSectionType::Faq => ['items' => $items = $this->faq((array) ($settings['items'] ?? []), $locale), 'ready' => $items !== []],
                PageSectionType::ImageText => [
                    'image' => is_string($settings['image'] ?? null) ? PageBuilderImageStore::url($settings['image']) : null,
                    'image_side' => ($settings['image_side'] ?? 'start') === 'end' ? 'end' : 'start',
                    'button' => $text('button'),
                    'url' => is_string($settings['url'] ?? null) ? $settings['url'] : null,
                    'ready' => $section['title'] !== null || $section['body'] !== null,
                ],
            };

            if ($section['ready']) {
                $sections[] = $section;
            }
        }

        return $sections;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function plain(StorePage $page): array
    {
        return array_map(static function (array $section): array {
            if (isset($section['cards'])) {
                $section['products'] = CatalogCards::present($section['cards'], request());
                unset($section['cards']);
            }

            if (isset($section['banners'])) {
                $section['banners'] = array_map(static fn (BannerData $banner): array => $banner->toArray(), $section['banners']);
            }

            if (isset($section['categories'])) {
                $section['categories'] = array_map(static fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => (string) $category->name,
                    'url' => route('storefront.category', $category->slug),
                    'image_url' => $category->thumbnailUrl(),
                ], $section['categories']);
            }

            unset($section['component'], $section['ready']);

            return $section;
        }, $this->render($page));
    }

    /**
     * Picked categories (visible ones, picked order), or the top categories when none picked.
     *
     * @param  list<string>  $refs
     * @return list<Category>
     */
    private function categoryList(array $refs, int $limit): array
    {
        $visible = $this->categories->visible();

        if ($refs === []) {
            return array_slice(array_map(static fn (array $node): Category => $node['category'], $this->categories->tree()), 0, $limit);
        }

        $list = [];

        foreach ($refs as $key) {
            $ref = StoreCategoryRef::tryParse($key);
            $category = $ref === null ? null : $visible->get($ref->source === StoreCategorySource::Central ? $ref->id : StoreOwnCategory::idFor($ref->id));

            if ($category instanceof Category && count($list) < $limit) {
                $list[] = $category;
            }
        }

        return $list;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{question: string, answer: string}>
     */
    private function faq(array $rows, string $locale): array
    {
        $items = [];

        foreach ($rows as $row) {
            $question = $row['q'][$locale] ?? null;
            $answer = $row['a'][$locale] ?? null;

            if (is_string($question) && is_string($answer) && $question !== '' && $answer !== '') {
                $items[] = ['question' => $question, 'answer' => $answer];
            }
        }

        return $items;
    }
}
