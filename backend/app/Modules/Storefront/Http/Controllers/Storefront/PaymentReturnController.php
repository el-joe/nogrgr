<?php

namespace App\Modules\Storefront\Http\Controllers\Storefront;

use App\Modules\Identity\Models\Customer;
use App\Modules\Storefront\Enums\PaymentReturnOutcome;
use App\Modules\Storefront\Services\Checkout\PaymentFlow;
use App\Modules\Storefront\Services\Seo\StorefrontSeo;
use App\Modules\Storefront\Support\StorefrontView;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * Gateway return URL `checkout/payment/{gateway}/{order}?status=success|cancel&reference=`
 * (Commerce PaymentService::start). Success is confirmed with the gateway through
 * PaymentService::confirm; paid / pending / cancelled land on the order page with a
 * toast ("Payment completed successfully." / "Payment was cancelled."), a failed or
 * declined payment shows "Payment was not completed" (Figma 114:108918) with retry.
 */
class PaymentReturnController extends Controller
{
    public function __construct(
        private readonly PaymentFlow $payments,
        private readonly StorefrontSeo $seo,
    ) {}

    public function __invoke(Request $request, string $gateway, string $order): View|RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        if (! $customer instanceof Customer) {
            return redirect()->route('storefront.account.login', ['redirect' => $request->getRequestUri()]);
        }

        $model = $this->payments->order($customer, $order);
        $outcome = $this->payments->confirm($model, (string) $request->query('status', ''), (string) $request->query('reference', ''));

        if ($outcome === PaymentReturnOutcome::Failed) {
            return StorefrontView::make('pages.payment-failed', [
                'order' => $model,
                'gateway' => $gateway,
                'seo' => $this->seo->page(__st('checkout.failed.title'), null, index: false),
            ]);
        }

        return redirect()->to(self::orderUrl($model->uuid, true))->with($outcome->flashKey(), $outcome->message());
    }

    /**
     * The order page (sf-account, `storefront.orders.show`; `placed=1` = order-success card).
     */
    public static function orderUrl(string $uuid, bool $placed = false): string
    {
        if (Route::has('storefront.orders.show')) {
            return route('storefront.orders.show', array_filter(['order' => $uuid, 'placed' => $placed ? 1 : null]));
        }

        return route('storefront.home');
    }
}
