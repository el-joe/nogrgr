<?php

namespace App\Modules\Commerce\Http\Controllers\StoreApi;

use App\Modules\Commerce\DTOs\Checkout\CheckoutSelectionData;
use App\Modules\Commerce\DTOs\Checkout\PlaceOrderData;
use App\Modules\Commerce\Http\Concerns\ResolvesStoreApiContext;
use App\Modules\Commerce\Http\Requests\StoreApi\CheckoutOptionsRequest;
use App\Modules\Commerce\Http\Requests\StoreApi\PlaceOrderRequest;
use App\Modules\Commerce\Http\Resources\StoreApi\CheckoutOptionsResource;
use App\Modules\Commerce\Services\Checkout\CheckoutService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;

/**
 * Store API checkout (signed-in customers): options and idempotent place order.
 */
class CheckoutController extends Controller
{
    use ResolvesStoreApiContext;

    public function __construct(private readonly CheckoutService $checkout) {}

    public function options(CheckoutOptionsRequest $request): JsonResponse
    {
        $options = $this->checkout->options($this->cartContext($request), CheckoutSelectionData::fromRequest($request));

        return JsonResponder::success('', (new CheckoutOptionsResource($options))->toArray($request));
    }

    public function place(PlaceOrderRequest $request): JsonResponse
    {
        $result = $this->checkout->place($this->cartContext($request), PlaceOrderData::fromRequest($request));

        return JsonResponder::success((string) __('commerce::checkout.messages.placed'), $result, status: $result['replayed'] ? 200 : 201);
    }
}
