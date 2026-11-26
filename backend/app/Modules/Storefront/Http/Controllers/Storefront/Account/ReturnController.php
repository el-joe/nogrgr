<?php

namespace App\Modules\Storefront\Http\Controllers\Storefront\Account;

use App\Modules\Storefront\Http\Controllers\Storefront\Concerns\ServesCustomerPages;
use App\Modules\Storefront\Services\Account\AccountReturnPages;
use App\Modules\Storefront\Support\StorefrontView;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Account › Returns (SF-ECO-031), return detail with thread / reply / withdraw
 * (SF-ECO-032) and "Request a return" for an order (SF-ECO-030). Own returns and orders
 * only (404); the forms post to the Store API returns endpoints.
 */
class ReturnController extends Controller
{
    use ServesCustomerPages;

    public function __construct(private readonly AccountReturnPages $pages) {}

    public function index(Request $request): View
    {
        return StorefrontView::make('pages.returns.index', $this->pages->list($this->customer(), $request) + [
            'groups' => array_merge(['all'], array_keys(AccountReturnPages::GROUPS)),
            'seo' => $this->privateSeo(__st('returns.title')),
        ]);
    }

    public function show(Request $request, int $return): View
    {
        $detail = $this->orNotFound(fn (): array => $this->pages->detail($this->customer(), $request, $return));

        return StorefrontView::make('pages.returns.show', [
            'detail' => $detail,
            'seo' => $this->privateSeo($detail['reference']),
        ]);
    }

    public function create(Request $request, string $order): View
    {
        $options = $this->orNotFound(fn (): array => $this->pages->options($this->customer(), $request, $order));

        return StorefrontView::make('pages.returns.create', [
            'options' => $options,
            'seo' => $this->privateSeo(__st('returns.request_title')),
        ]);
    }
}
