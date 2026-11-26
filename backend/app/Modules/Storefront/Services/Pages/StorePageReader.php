<?php

namespace App\Modules\Storefront\Services\Pages;

use App\Modules\Storefront\Enums\SystemStorePage;
use App\Modules\Storefront\Models\StorePage;
use App\Modules\Storefront\Repositories\Contracts\StorePageRepositoryContract;
use Carbon\CarbonInterface;

/**
 * Storefront reads of the store CMS pages: one published page by slug, footer links and
 * sitemap entries (tenant context).
 */
class StorePageReader
{
    public function __construct(private readonly StorePageRepositoryContract $pages) {}

    public function find(string $slug): ?StorePage
    {
        return $this->pages->findBySlug($slug, activeOnly: true);
    }

    /**
     * Every published page in the store order (Store API page list).
     *
     * @return list<StorePage>
     */
    public function published(): array
    {
        return $this->pages->published()->values()->all();
    }

    /**
     * @return list<array{title: string, url: string, group: string}>
     */
    public function footerLinks(): array
    {
        return $this->pages->published(footerOnly: true)
            ->map(static fn (StorePage $page): array => [
                'title' => (string) $page->title,
                'url' => route('storefront.pages.show', $page->slug),
                'group' => SystemStorePage::tryFrom($page->slug)?->footerGroup() ?? 'company',
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{url: string, updated_at: CarbonInterface|null}>
     */
    public function sitemapEntries(): array
    {
        return $this->pages->published()
            ->map(static fn (StorePage $page): array => [
                'url' => route('storefront.pages.show', $page->slug),
                'updated_at' => $page->updated_at,
            ])
            ->values()
            ->all();
    }
}
