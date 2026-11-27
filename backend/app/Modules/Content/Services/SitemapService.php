<?php

namespace App\Modules\Content\Services;

use App\Modules\Content\Models\BlogCategory;
use App\Modules\Content\Models\BlogPost;
use App\Modules\Content\Models\Page;
use App\Modules\Content\Repositories\Contracts\BlogCategoryRepositoryContract;
use App\Modules\Content\Repositories\Contracts\BlogPostRepositoryContract;
use App\Modules\Content\Repositories\Contracts\PageRepositoryContract;
use App\Modules\Storefront\Models\ThemeCategory;
use App\Modules\Storefront\Repositories\Contracts\ThemeRepositoryContract;
use App\Shared\Services\BaseService;
use App\Shared\Support\Locale;
use Illuminate\Support\Carbon;

/**
 * Central sitemap.xml: every public central URL once per enabled locale (`?lang=`),
 * each with its hreflang alternates (xhtml:link).
 */
class SitemapService extends BaseService
{
    public function __construct(
        private readonly PageRepositoryContract $pages,
        private readonly BlogPostRepositoryContract $posts,
        private readonly BlogCategoryRepositoryContract $categories,
        private readonly ThemeRepositoryContract $themes,
    ) {}

    /**
     * @return list<array{loc: string, lastmod: string|null, alternates: array<string, string>}>
     */
    public function entries(): array
    {
        $paths = [];

        foreach (['central.home', 'central.templates', 'central.themes', 'central.pricing', 'central.how-it-works', 'central.contact', 'central.faqs', 'central.blog.index', 'central.register'] as $name) {
            $paths[] = [route($name), null];
        }

        foreach ($this->pages->publicPages() as $page) {
            /** @var Page $page */
            $paths[] = [$page->url(), $page->updated_at];
        }

        foreach ($this->themes->categories() as $category) {
            /** @var ThemeCategory $category */
            $paths[] = [route('central.templates.category', $category->slug), null];
        }

        foreach ($this->categories->activeWithCounts() as $category) {
            /** @var BlogCategory $category */
            if (($category->posts_count ?? 0) > 0) {
                $paths[] = [route('central.blog.index', ['category' => $category->slug]), null];
            }
        }

        foreach ($this->posts->allVisible() as $post) {
            /** @var BlogPost $post */
            $paths[] = [$post->url(), $post->updated_at];
        }

        $locales = array_keys(Locale::enabled());
        $entries = [];

        foreach ($paths as [$url, $modified]) {
            /** @var Carbon|null $modified */
            $alternates = [];

            foreach ($locales as $locale) {
                $alternates[$locale] = $this->localized($url, $locale);
            }

            foreach ($alternates as $loc) {
                $entries[] = ['loc' => $loc, 'lastmod' => $modified?->toAtomString(), 'alternates' => $alternates];
            }
        }

        return $entries;
    }

    private function localized(string $url, string $locale): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').'lang='.$locale;
    }
}
