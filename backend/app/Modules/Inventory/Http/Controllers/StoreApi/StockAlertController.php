<?php

namespace App\Modules\Inventory\Http\Controllers\StoreApi;

use App\Modules\Api\Support\StoreApiCustomer;
use App\Modules\Inventory\Http\Requests\StoreApi\StockAlertRequest;
use App\Modules\Inventory\Services\Alerts\BackInStockService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Back-in-stock subscriptions of the Store API (themes' "Notify me" form + mobile app):
 * status of the signed-in customer, subscribe (guest e-mail or account e-mail).
 */
class StockAlertController extends Controller
{
    public function __construct(private readonly BackInStockService $alerts) {}

    public function show(Request $request, int $storeProduct): JsonResponse
    {
        return JsonResponder::success('', ['subscribed' => $this->alerts->isSubscribed($storeProduct, StoreApiCustomer::resolve($request))]);
    }

    public function store(StockAlertRequest $request, int $storeProduct): JsonResponse
    {
        $data = $request->toData();
        $subscription = $this->alerts->subscribe($storeProduct, StoreApiCustomer::resolve($request), $data->email, $data->locale);

        return JsonResponder::created(__st('product.stock_alert.subscribed', ['email' => $subscription->email]), [
            'subscribed' => true,
            'email' => $subscription->email,
        ]);
    }
}
