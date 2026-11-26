<?php

namespace App\Modules\Storefront\Http\Controllers\Storefront\Account;

use App\Modules\Storefront\Http\Controllers\Storefront\Concerns\ServesCustomerPages;
use App\Modules\Storefront\Services\Account\FavoritesPage;
use App\Modules\Storefront\Support\StorefrontView;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Account › Wishlist (SF-ECO-033): favorited products as cards, search, empty state.
 * Removing posts to storefront.favorites.toggle; add to cart uses the Store API cart.
 */
class FavoritesController extends Controller
{
    use ServesCustomerPages;

    public function __construct(private readonly FavoritesPage $page) {}

    public function __invoke(Request $request): View
    {
        $search = is_string($request->query('q')) ? mb_substr(trim($request->query('q')), 0, 100) : null;

        return StorefrontView::make('pages.favorites.index', $this->page->cards($this->customer(), $search) + [
            'search' => $search,
            'seo' => $this->privateSeo(__st('account.wishlist.title')),
        ]);
    }
}
