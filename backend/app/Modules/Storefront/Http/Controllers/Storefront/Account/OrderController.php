<?php

namespace App\Modules\Storefront\Http\Controllers\Storefront\Account;

use App\Modules\Storefront\Http\Controllers\Storefront\Concerns\ServesCustomerPages;
use App\Modules\Storefront\Services\Account\AccountOrderPages;
use App\Modules\Storefront\Support\StorefrontView;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Account › Orders (SF-ECO-027), order details + order success (SF-ECO-024/028), tracking
 * (SF-ECO-029) and the printable invoice. Own orders of the signed-in customer only (404).
 * Cancel, review and reorder post to the Store API from the page.
 */
class OrderController extends Controller
{
    use ServesCustomerPages;

    public function __construct(private readonly AccountOrderPages $pages) {}

    public function index(Request $request): View
    {
        $tab = AccountOrderPages::tab($request->query('status'));
        $search = is_string($request->query('q')) ? mb_substr(trim($request->query('q')), 0, 100) : null;
        $history = $this->pages->history($this->customer(), $request, $tab, $search);

        return StorefrontView::make('pages.orders.index', $history + [
            'tab' => $tab,
            'tabs' => array_keys(AccountOrderPages::TABS),
            'search' => $search,
            'seo' => $this->privateSeo(__st('orders.title')),
        ]);
    }

    public function show(Request $request, string $order): View
    {
        $detail = $this->orNotFound(fn (): array => $this->pages->detail($this->customer(), $request, $order));

        return StorefrontView::make($request->boolean('placed') ? 'pages.orders.success' : 'pages.orders.show', [
            'detail' => $detail,
            'panel' => 'details',
            'customer' => $this->customer(),
            'seo' => $this->privateSeo(__st('orders.details_title', ['number' => $detail['number']])),
        ]);
    }

    public function tracking(Request $request, string $order): View
    {
        $detail = $this->orNotFound(fn (): array => $this->pages->detail($this->customer(), $request, $order));

        return StorefrontView::make('pages.orders.show', [
            'detail' => $detail,
            'panel' => 'tracking',
            'customer' => $this->customer(),
            'seo' => $this->privateSeo(__st('orders.tracking_title', ['number' => $detail['number']])),
        ]);
    }

    public function invoice(Request $request, string $order): View
    {
        $detail = $this->orNotFound(fn (): array => $this->pages->detail($this->customer(), $request, $order));

        return StorefrontView::make('pages.orders.invoice', [
            'detail' => $detail,
            'seo' => $this->privateSeo(__st('orders.invoice_title', ['number' => $detail['number']])),
        ]);
    }
}
