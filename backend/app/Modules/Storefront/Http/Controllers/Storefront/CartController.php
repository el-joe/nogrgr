<?php

namespace App\Modules\Storefront\Http\Controllers\Storefront;

use App\Modules\Storefront\Enums\ListingKind;
use App\Modules\Storefront\Services\Catalog\HomeService;
use App\Modules\Storefront\Services\Checkout\StorefrontCart;
use App\Modules\Storefront\Services\Seo\StorefrontSeo;
use App\Modules\Storefront\Support\StorefrontView;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cart page (SF-ECO-022 / 022a, Figma 1:1348 · m 1:3210 · empty 1:1240 · m 1:3329) and
 * the server-rendered fragments the cart scripts swap in after a Store API mutation:
 * the cart body (`cart.fragment`) and the header mini cart (`cart.mini`, 1:1662).
 * Mutations themselves go through /store-api/v1/cart (never priced here).
 */
class CartController extends Controller
{
    public function __construct(
        private readonly StorefrontCart $cart,
        private readonly HomeService $home,
        private readonly StorefrontSeo $seo,
    ) {}

    public function index(Request $request): View
    {
        $picks = $this->home->grid(ListingKind::BestSelling);

        return StorefrontView::make('pages.cart', [
            'cart' => $this->cart->data($request),
            'picks' => $picks,
            'picksMoreUrl' => $picks->hasMore() ? route('storefront.best-sellers', ['page' => 2, 'per' => HomeService::GRID]) : null,
            'seo' => $this->seo->page(__st('cart.title'), null, index: false),
        ]);
    }

    public function fragment(Request $request): JsonResponse
    {
        $cart = $this->cart->data($request);

        return JsonResponder::success(data: [
            'html' => view(StorefrontView::component('cart.body'), ['cart' => $cart])->render(),
            'summary' => view(StorefrontView::component('cart.summary'), ['cart' => $cart])->render(),
            'items_count' => $cart['items_count'],
            'empty' => $cart['lines'] === [],
        ]);
    }

    public function mini(Request $request): JsonResponse
    {
        $cart = $this->cart->data($request);

        return JsonResponder::success(data: [
            'html' => view(StorefrontView::component('cart.mini-body'), ['cart' => $cart])->render(),
            'items_count' => $cart['items_count'],
        ]);
    }
}
