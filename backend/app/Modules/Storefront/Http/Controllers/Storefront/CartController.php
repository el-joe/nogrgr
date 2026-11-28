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
        $cart = $this->cart->data($request);

        return StorefrontView::make('pages.cart', [
            'cart' => $cart,
            'rails' => $this->home->cartRails(array_map(static fn (array $line): int => (int) $line['store_product_id'], $cart['lines'])),
            'picks' => $picks,
            'picksMoreUrl' => $picks->hasMore() ? route('storefront.best-sellers', ['page' => 2, 'per' => HomeService::GRID]) : null,
            'seo' => $this->seo->page(__st('cart.title'), null, index: false),
        ]);
    }

    public function fragment(Request $request): JsonResponse
    {
        $cart = $this->cart->data($request);

        $data = [
            'html' => view(StorefrontView::component('cart.body'), ['cart' => $cart])->render(),
            'summary' => view(StorefrontView::component('cart.summary'), ['cart' => $cart])->render(),
            'items_count' => $cart['items_count'],
            'empty' => $cart['lines'] === [],
        ];

        // Themes whose cart rails skip the products already in the cart (Elora) get them again
        // when those products changed; `rails` = the key the page holds, so a quantity change
        // keeps the rails (and their swipe position) as they are.
        $rails = StorefrontView::component('cart.rails');

        if ($request->has('rails') && view()->exists($rails)) {
            $ids = array_map(static fn (array $line): int => (int) $line['store_product_id'], $cart['lines']);
            $unique = array_unique($ids);
            sort($unique);
            $key = implode(',', $unique);

            if ($key !== $request->query('rails')) {
                $data['rails'] = view($rails, ['rails' => $this->home->cartRails($ids)])->render();
                $data['rails_key'] = $key;
            }
        }

        return JsonResponder::success(data: $data);
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
