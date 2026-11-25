<?php

namespace App\Modules\Commerce\Http\Controllers\StoreApi;

use App\Modules\Commerce\Http\Concerns\ResolvesStoreApiContext;
use App\Modules\Commerce\Http\Requests\StoreApi\CancelOrderRequest;
use App\Modules\Commerce\Http\Requests\StoreApi\OrderIndexRequest;
use App\Modules\Commerce\Http\Resources\StoreApi\OrderResource;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Services\Orders\CustomerOrderService;
use App\Modules\Commerce\Services\Orders\OrderLifecycleService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Store API customer orders: history (status filter, search), detail, cancel.
 */
class OrderController extends Controller
{
    use ResolvesStoreApiContext;

    public function __construct(
        private readonly CustomerOrderService $orders,
        private readonly OrderLifecycleService $lifecycle,
    ) {}

    public function index(OrderIndexRequest $request): JsonResponse
    {
        $this->cartContext($request);
        $page = $this->orders->history($this->requireCustomer($request), [
            'status' => $request->validated('status'),
            'search' => $request->validated('search'),
        ], (int) ($request->validated('per_page') ?? 15));

        return JsonResponder::success('', [
            'orders' => collect($page->items())->map(static fn (Order $order): array => (new OrderResource($order))->toArray($request))->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function show(Request $request, string $order): JsonResponse
    {
        $this->cartContext($request);
        $model = $this->orders->find($this->requireCustomer($request), $order);

        return JsonResponder::success('', OrderResource::detail($model)->toArray($request));
    }

    public function cancel(CancelOrderRequest $request, string $order): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $model = $this->orders->find($customer, $order);
        $reason = $request->validated('reason');
        $this->lifecycle->cancelByCustomer($model, $customer->id, is_string($reason) ? $reason : null);

        return JsonResponder::success((string) __('commerce::orders.messages.cancelled'), OrderResource::detail($this->orders->find($customer, $order))->toArray($request));
    }
}
