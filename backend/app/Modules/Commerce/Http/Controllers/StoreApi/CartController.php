<?php

namespace App\Modules\Commerce\Http\Controllers\StoreApi;

use App\Modules\Commerce\DTOs\Cart\AddCartItemData;
use App\Modules\Commerce\DTOs\Cart\CartSummary;
use App\Modules\Commerce\Http\Concerns\ResolvesStoreApiContext;
use App\Modules\Commerce\Http\Requests\StoreApi\AddCartItemRequest;
use App\Modules\Commerce\Http\Requests\StoreApi\ApplyCouponRequest;
use App\Modules\Commerce\Http\Requests\StoreApi\SwitchCountryRequest;
use App\Modules\Commerce\Http\Requests\StoreApi\UpdateCartItemRequest;
use App\Modules\Commerce\Http\Resources\StoreApi\CartResource;
use App\Modules\Commerce\Services\Cart\CartService;
use App\Modules\Storefront\Services\StorefrontContext;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Store API cart (guests by X-Cart-Token, customers by token / session; merged on login).
 */
class CartController extends Controller
{
    use ResolvesStoreApiContext;

    public function __construct(private readonly CartService $carts) {}

    public function show(Request $request): JsonResponse
    {
        return $this->respond($request, $this->carts->summary($this->cartContext($request)));
    }

    public function store(AddCartItemRequest $request): JsonResponse
    {
        $summary = $this->carts->add($this->cartContext($request), AddCartItemData::fromRequest($request));

        return $this->respond($request, $summary, __('commerce::cart.messages.added'), 201);
    }

    public function update(UpdateCartItemRequest $request, string $item): JsonResponse
    {
        $summary = $this->carts->updateQuantity($this->cartContext($request), (int) $item, (int) $request->validated('quantity'));

        return $this->respond($request, $summary, __('commerce::cart.messages.updated'));
    }

    public function destroy(Request $request, string $item): JsonResponse
    {
        return $this->respond($request, $this->carts->remove($this->cartContext($request), (int) $item), __('commerce::cart.messages.removed'));
    }

    public function applyCoupon(ApplyCouponRequest $request): JsonResponse
    {
        $summary = $this->carts->applyCoupon($this->cartContext($request), (string) $request->validated('code'));

        return $this->respond($request, $summary, __('commerce::cart.messages.coupon_applied'));
    }

    public function removeCoupon(Request $request): JsonResponse
    {
        return $this->respond($request, $this->carts->removeCoupon($this->cartContext($request)), __('commerce::cart.messages.coupon_removed'));
    }

    public function country(SwitchCountryRequest $request): JsonResponse
    {
        $cityId = $request->validated('city_id');
        $summary = $this->carts->switchCountry($this->cartContext($request), (int) $request->validated('country_id'), $cityId !== null ? (int) $cityId : null);

        // A theme session switching country keeps browsing in it.
        if ($request->hasSession() && ! $request->hasHeader('X-Tenant')) {
            app(StorefrontContext::class)->remember((int) $request->validated('country_id'), null);
        }

        return $this->respond($request, $summary, __('commerce::cart.messages.country_changed'));
    }

    private function respond(Request $request, CartSummary $summary, mixed $message = '', int $status = 200): JsonResponse
    {
        $this->rememberCartToken($request, $summary->cart?->token);

        return JsonResponder::success((string) $message, (new CartResource($summary))->toArray($request), status: $status);
    }
}
