<?php

namespace App\Modules\Storefront\Http\Controllers\StoreApi;

use App\Modules\Storefront\Http\Controllers\StoreApi\Concerns\PreparesCatalogContext;
use App\Modules\Storefront\Http\Resources\StoreApi\CategoryResource;
use App\Modules\Storefront\Http\Resources\StoreApi\StorePageResource;
use App\Modules\Storefront\Models\StorePage;
use App\Modules\Storefront\Services\Appearance\StorefrontAppearance;
use App\Modules\Storefront\Services\Catalog\CategoryCatalog;
use App\Modules\Storefront\Services\Catalog\StorefrontShell;
use App\Modules\Storefront\Services\Layouts\PageSectionRenderer;
use App\Modules\Storefront\Services\Pages\StorePageReader;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Store API content (APP-030 / APP-031): published CMS pages and the store shell — header
 * links + category menu, footer columns (store pages + own links), legal links, social
 * links, about / copyright lines, promo bar and payment badges (same data as the theme).
 */
class ContentController extends Controller
{
    use PreparesCatalogContext;

    public function __construct(
        private readonly StorePageReader $pages,
        private readonly StorefrontShell $shell,
        private readonly StorefrontAppearance $appearance,
        private readonly CategoryCatalog $categories,
    ) {}

    public function pages(Request $request): JsonResponse
    {
        return JsonResponder::success('', [
            'items' => array_map(static fn (StorePage $page): array => (new StorePageResource($page, withBody: false))->toArray($request), $this->pages->published()),
        ]);
    }

    public function page(Request $request, string $slug): JsonResponse
    {
        $page = $this->pages->find($slug);

        if ($page === null) {
            return JsonResponder::error((string) __('api::store.page_not_found'), 404);
        }

        $this->prepareCatalog($request);

        // Page-builder sections of the page (VEN-032), the same the web page renders.
        return JsonResponder::success('', (new StorePageResource($page))->toArray($request) + ['sections' => app(PageSectionRenderer::class)->plain($page)]);
    }

    public function shell(Request $request): JsonResponse
    {
        $this->prepareCatalog($request);
        $footer = $this->shell->footerLinks();

        return JsonResponder::success('', [
            'header_links' => $this->appearance->headerLinks(),
            'menu' => array_map(static fn (array $node): array => (new CategoryResource($node['category'], $node['children']))->toArray($request), $this->categories->menu()),
            'footer' => [
                'columns' => $footer['columns'],
                'legal' => $footer['legal'],
                'about' => $this->appearance->aboutText(),
                'copyright' => $this->appearance->copyright(),
            ],
            'social' => $this->appearance->socialLinks(),
            'promo_bar' => $this->appearance->promoBar(),
            'payment_badges' => $this->shell->paymentBadges(),
        ]);
    }
}
