<?php

namespace App\Modules\Commerce\Http\Controllers\StoreApi;

use App\Modules\Commerce\Http\Concerns\ResolvesStoreApiContext;
use App\Modules\Commerce\Http\Requests\StoreApi\ConfirmPaymentRequest;
use App\Modules\Commerce\Http\Requests\StoreApi\StartPaymentRequest;
use App\Modules\Commerce\Http\Resources\StoreApi\OrderResource;
use App\Modules\Commerce\Http\Resources\StoreApi\PaymentResource;
use App\Modules\Commerce\Services\Orders\CustomerOrderService;
use App\Modules\Commerce\Services\Payments\PaymentService;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Store API payments: start an attempt for an order's checkout group, confirm on return,
 * and gateway webhooks (signature-checked, replay-safe; no customer).
 */
class PaymentController extends Controller
{
    use ResolvesStoreApiContext;

    public function __construct(
        private readonly PaymentService $payments,
        private readonly CustomerOrderService $orders,
    ) {}

    public function start(StartPaymentRequest $request, string $order): JsonResponse
    {
        $this->cartContext($request);
        $model = $this->orders->find($this->requireCustomer($request), $order);
        $started = $this->payments->start($model, PaymentGatewayCode::from((string) $request->validated('gateway')), $request->getSchemeAndHttpHost());

        return JsonResponder::success('', [
            'payment' => (new PaymentResource($started->payment))->toArray($request),
            'redirect_url' => $started->redirectUrl,
            // Inline Stripe card (decision #78 b): confirm with Stripe.js / the Stripe SDK, then POST confirm.
            'client_secret' => $started->clientSecret,
            'publishable_key' => $started->publishableKey,
        ], status: 201);
    }

    public function confirm(ConfirmPaymentRequest $request, string $order): JsonResponse
    {
        $this->cartContext($request);
        $customer = $this->requireCustomer($request);
        $payment = $this->payments->confirm($this->orders->find($customer, $order), (string) $request->validated('reference'));

        return JsonResponder::success((string) __('commerce::orders.payment.'.$payment->status->value), [
            'payment' => (new PaymentResource($payment))->toArray($request),
            'order' => OrderResource::detail($this->orders->find($customer, $order))->toArray($request),
        ]);
    }

    public function webhook(Request $request, string $gateway): JsonResponse
    {
        $code = PaymentGatewayCode::tryFrom($gateway);

        if ($code === null) {
            return JsonResponder::error((string) __('commerce::orders.errors.gateway_unknown'), 404);
        }

        try {
            $result = $this->payments->webhook($code, $request);
        } catch (PaymentGatewayException) {
            return JsonResponder::error((string) __('commerce::orders.errors.webhook_rejected'), 400);
        }

        return JsonResponder::success($result);
    }
}
