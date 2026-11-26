<?php

namespace App\Modules\Storefront\Http\Controllers\Storefront;

use App\Modules\Commerce\DTOs\Checkout\CheckoutSelectionData;
use App\Modules\Identity\Models\Customer;
use App\Modules\Storefront\Services\Checkout\CheckoutPage;
use App\Modules\Storefront\Services\Checkout\PaymentFlow;
use App\Modules\Storefront\Services\Checkout\StorefrontCart;
use App\Modules\Storefront\Services\Seo\StorefrontSeo;
use App\Modules\Storefront\Support\StorefrontView;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Checkout (SF-ECO-023, Figma 1:1520 · m 1:3540 + Storefront additions 98:100243 /
 * 98:100522 / m 98:100610 / 98:100707): signed-in customers only (guests → account login
 * with ?redirect=/checkout), an empty cart goes back to the cart. The summary fragment
 * re-prices for the selected address (CheckoutService::options); placing the order and
 * starting the payment are Store API calls made by the page script. `pay` is the
 * payment step of an already placed order (redirect gateways, retry — SF-ECO-023b).
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutPage $page,
        private readonly PaymentFlow $payments,
        private readonly StorefrontSeo $seo,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $customer = $this->customer();

        if ($customer === null) {
            return redirect()->route('storefront.account.login', ['redirect' => '/checkout']);
        }

        $data = $this->page->data($request, $customer, new CheckoutSelectionData);

        if ($data['cart']['lines'] === []) {
            return redirect()->route('storefront.cart');
        }

        return StorefrontView::make('pages.checkout', $data + [
            'seo' => $this->seo->page(__st('checkout.title'), null, index: false),
        ]);
    }

    public function fragment(Request $request): JsonResponse
    {
        $customer = $this->customer();
        abort_if($customer === null, 401);

        $validated = $request->validate([
            'shipping_address_id' => ['nullable', 'integer', 'min:1'],
            'billing_address_id' => ['nullable', 'integer', 'min:1'],
            'billing_same_as_shipping' => ['sometimes', 'boolean'],
        ]);

        $data = $this->page->data($request, $customer, new CheckoutSelectionData(
            shippingAddressId: isset($validated['shipping_address_id']) ? (int) $validated['shipping_address_id'] : null,
            billingAddressId: isset($validated['billing_address_id']) ? (int) $validated['billing_address_id'] : null,
            billingSameAsShipping: $request->boolean('billing_same_as_shipping', true),
        ));

        return JsonResponder::success(data: [
            'summary' => view('components.storefront.checkout.summary', ['cart' => $data['cart']])->render(),
            'shipping' => $data['shipping'],
            'block' => $data['block'],
            'block_message' => $data['options']['block_message'],
            'pay_total' => StorefrontCart::price($data['cart']['totals']['grand_total']),
            'empty' => $data['cart']['lines'] === [],
            'gateways' => count($data['gateways']),
        ]);
    }

    public function pay(Request $request, string $order): View|RedirectResponse
    {
        $customer = $this->customer();

        if ($customer === null) {
            return redirect()->route('storefront.account.login', ['redirect' => $request->getRequestUri()]);
        }

        $model = $this->payments->order($customer, $order);

        if (! $this->payments->payable($model)) {
            return redirect()->to(PaymentReturnController::orderUrl($model->uuid));
        }

        $gateway = (string) $request->query('gateway', '');
        $data = $this->payments->payPage($model);
        $selected = null;

        foreach ($data['gateways'] as $option) {
            if ($option['code'] === $gateway) {
                $selected = $option;
            }
        }
        $redirecting = $selected !== null && ! $selected['inline'];

        return StorefrontView::make($redirecting ? 'pages.payment-redirect' : 'pages.payment-pay', $data + [
            'gateway' => $selected,
            'seo' => $this->seo->page(__st('checkout.pay_title'), null, index: false),
        ]);
    }

    private function customer(): ?Customer
    {
        $customer = Auth::guard('customer')->user();

        return $customer instanceof Customer ? $customer : null;
    }
}
