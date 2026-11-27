<?php

namespace App\Modules\Storefront\Http\Controllers\Storefront;

use App\Modules\Storefront\Enums\SystemStorePage;
use App\Modules\Storefront\Services\Layouts\PageSectionRenderer;
use App\Modules\Storefront\Services\Pages\StorePageReader;
use App\Modules\Storefront\Services\Seo\StorefrontSeo;
use App\Modules\Storefront\Support\StorefrontView;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

/**
 * Store CMS page (SF-ECO-034, Figma Storefront additions 114:107865 / m 114:108055):
 * about, terms, privacy, … and the store's own pages. The contact page also shows the
 * support form (SF-ECO-035). Unknown or switched-off pages → 404.
 */
class PageController extends Controller
{
    public function __construct(
        private readonly StorePageReader $pages,
        private readonly StorefrontSeo $seo,
    ) {}

    public function __invoke(string $slug): View
    {
        $page = $this->pages->find($slug);

        abort_if($page === null, 404);

        $url = route('storefront.pages.show', $page->slug);
        $menu = $this->pages->footerLinks();

        if (! in_array($url, array_column($menu, 'url'), true)) {
            $menu[] = ['title' => (string) $page->title, 'url' => $url, 'group' => 'company'];
        }

        return StorefrontView::make('pages.page', [
            'page' => $page,
            'sections' => app(PageSectionRenderer::class)->render($page),
            'showSupportForm' => $page->slug === SystemStorePage::Contact->value,
            'menu' => $menu,
            'currentUrl' => $url,
            'seo' => $this->seo->page((string) $page->title, $page->meta_description ?: Str::limit(trim(strip_tags((string) $page->body)), 160)),
        ]);
    }
}
