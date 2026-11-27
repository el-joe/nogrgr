<?php

namespace App\Modules\Support\Http\Controllers\Storefront;

use App\Modules\Engagement\Enums\SupportSubject;
use App\Modules\Identity\Models\Customer;
use App\Modules\Storefront\Services\Seo\StorefrontSeo;
use App\Modules\Storefront\Support\StorefrontView;
use App\Modules\Support\Services\CustomerTicketService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Theme account › Support (all families): the customer's tickets with status chips and the
 * "New ticket" sheet, and one ticket's conversation with reply / close. The forms post to
 * the Store API support endpoints; another customer's ticket → 404.
 */
class CustomerTicketPageController extends Controller
{
    public function __construct(private readonly CustomerTicketService $tickets) {}

    public function index(Request $request): View
    {
        $customer = $this->customer();
        $group = in_array($request->query('status'), ['active', 'closed'], true) ? (string) $request->query('status') : null;

        return StorefrontView::make('pages.support.index', [
            'page' => $this->tickets->list($customer, $group, 10)->withQueryString(),
            'group' => $group ?? 'all',
            'topics' => SupportSubject::storefrontOptions(),
            'orders' => $this->tickets->recentOrders($customer),
            'seo' => app(StorefrontSeo::class)->page(__st('support.tickets.title'), index: false),
        ]);
    }

    public function show(int $ticket): View
    {
        $row = $this->tickets->findForCustomer($this->customer(), $ticket);
        $this->tickets->openByCustomer($row);

        return StorefrontView::make('pages.support.show', [
            'ticket' => $row,
            'messages' => $this->tickets->messages($row),
            'seo' => app(StorefrontSeo::class)->page($row->displayReference(), index: false),
        ]);
    }

    private function customer(): Customer
    {
        $customer = Auth::guard('customer')->user();

        abort_unless($customer instanceof Customer, 403);

        return $customer;
    }
}
