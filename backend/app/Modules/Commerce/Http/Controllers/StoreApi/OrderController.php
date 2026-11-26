<?php

namespace App\Modules\Commerce\Http\Controllers\StoreApi;

use App\Modules\Commerce\Enums\CancellationReason;
use App\Modules\Commerce\Http\Concerns\ResolvesStoreApiContext;
use App\Modules\Commerce\Http\Requests\StoreApi\CancelOrderRequest;
use App\Modules\Commerce\Http\Requests\StoreApi\OrderIndexRequest;
use App\Modules\Commerce\Http\Resources\StoreApi\CartResource;
use App\Modules\Commerce\Http\Resources\StoreApi\OrderResource;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Services\Orders\CustomerOrderService;
use App\Modules\Commerce\Services\Orders\OrderInvoiceLinks;
use App\Modules\Commerce\Services\Orders\OrderLifecycleService;
use App\Modules\Commerce\Services\Orders\ReorderService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Store API customer orders: history (status filter, search), detail, cancellation
 * reasons + cancel, reorder (available lines back into the cart) and the signed invoice
 * link (printable page on the store domain).
 */
class OrderController extends Controller
{
    use ResolvesStoreApiContext;

    public function __construct(
        private readonly CustomerOrderService $orders,
        private readonly OrderLifecycleService $lifecycle,
        private readonly ReorderService $reorders,
        private readonly OrderInvoiceLinks $invoices,
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

    /**
     * Reasons a customer can pick when cancelling ("other" needs a note in `reason`).
     */
    public function cancellationReasons(): JsonResponse
    {
        $reasons = [];

        foreach (CancellationReason::customerOptions() as $value => $label) {
            $reasons[] = ['value' => $value, 'label' => $label, 'requires_note' => CancellationReason::from($value)->requiresNote()];
        }

        return JsonResponder::success('', ['reasons' => $reasons]);
    }

    public function cancel(CancelOrderRequest $request, string $order): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $model = $this->orders->find($customer, $order);
        $this->lifecycle->cancelByCustomer($model, $customer->id, $request->note(), $request->reasonCode());

        return JsonResponder::success((string) __('commerce::orders.messages.cancelled'), OrderResource::detail($this->orders->find($customer, $order))->toArray($request));
    }

    public function reorder(Request $request, string $order): JsonResponse
    {
        $context = $this->cartContext($request);
        $model = $this->orders->find($this->requireCustomer($request), $order);
        $result = $this->reorders->reorder($context, $model);
        $this->rememberCartToken($request, $result['summary']->cart?->token);

        return JsonResponder::success((string) __('commerce::orders.messages.reordered'), [
            'cart' => (new CartResource($result['summary']))->toArray($request),
            'added' => $result['added'],
            'skipped' => $result['skipped'],
        ]);
    }

    public function invoice(Request $request, string $order): JsonResponse
    {
        $model = $this->orders->find($this->requireCustomer($request), $order);

        return JsonResponder::success((string) __('commerce::orders.messages.invoice_ready'), $this->invoices->link($model));
    }
}
