<?php

namespace App\Modules\AfterSales\Http\Controllers\StoreApi;

use App\Modules\AfterSales\Http\Requests\StoreApi\CreateCustomerReturnRequest;
use App\Modules\AfterSales\Http\Requests\StoreApi\ReturnEstimateRequest;
use App\Modules\AfterSales\Http\Requests\StoreApi\ReturnIndexRequest;
use App\Modules\AfterSales\Http\Requests\StoreApi\ReturnReplyRequest;
use App\Modules\AfterSales\Http\Requests\StoreApi\WithdrawReturnRequest;
use App\Modules\AfterSales\Http\Resources\StoreApi\ReturnOptionsResource;
use App\Modules\AfterSales\Http\Resources\StoreApi\ReturnResource;
use App\Modules\AfterSales\Models\ReturnReason;
use App\Modules\AfterSales\Models\ReturnRequest;
use App\Modules\AfterSales\Services\CustomerReturnService;
use App\Modules\AfterSales\Services\ReturnPolicyService;
use App\Modules\Commerce\Http\Concerns\ResolvesStoreApiContext;
use App\Modules\Commerce\Services\Orders\CustomerOrderService;
use App\Modules\Commerce\Support\MoneyPresenter;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Store API customer returns (SF-ECO-030/031/032, APP-016/017/018): request-return
 * options, create, list (all / per order), detail, reply, withdraw, and the evidence
 * files behind temporary signed URLs. Own returns of the current store only (404).
 */
class ReturnController extends Controller
{
    use ResolvesStoreApiContext;

    public function __construct(
        private readonly CustomerReturnService $returns,
        private readonly CustomerOrderService $orders,
        private readonly ReturnPolicyService $policies,
    ) {}

    public function options(Request $request, string $order): JsonResponse
    {
        $this->cartContext($request);
        $model = $this->orders->find($this->requireCustomer($request), $order);

        return JsonResponder::success('', (new ReturnOptionsResource($this->returns->options($this->tenantId(), $model), $model))->toArray($request));
    }

    public function estimate(ReturnEstimateRequest $request, string $order, MoneyPresenter $money): JsonResponse
    {
        $this->cartContext($request);
        $model = $this->orders->find($this->requireCustomer($request), $order);
        $estimate = $this->returns->estimate($this->tenantId(), $model, $request->reasonCode(), $request->lines());

        return JsonResponder::success('', [
            'items' => $money->present($estimate['items']->minor),
            'shipping' => $money->present($estimate['shipping']->minor),
            'return_fee' => $money->present($estimate['fee']->minor),
            'total' => $money->present($estimate['total']->minor),
            'fee_waived' => $estimate['fee_waived'],
            'paid' => $estimate['paid'],
        ]);
    }

    public function store(CreateCustomerReturnRequest $request, string $order): JsonResponse
    {
        $this->cartContext($request);
        $customer = $this->requireCustomer($request);
        $model = $this->orders->find($customer, $order);
        $created = $this->returns->create($this->tenantId(), $customer, $model, $request->toData());
        $reasons = $this->reasons();

        return JsonResponder::created((string) __('after-sales::messages.store_api.created'), [
            'returns' => array_map(fn (ReturnRequest $return): array => ReturnResource::detail($this->returns->find($this->tenantId(), $customer, $return->id), $reasons)->toArray($request), $created),
        ]);
    }

    public function index(ReturnIndexRequest $request): JsonResponse
    {
        $this->cartContext($request);

        return $this->page($request, $this->returns->list($this->tenantId(), $this->requireCustomer($request), null, $request->perPage()));
    }

    public function forOrder(ReturnIndexRequest $request, string $order): JsonResponse
    {
        $this->cartContext($request);
        $customer = $this->requireCustomer($request);
        $model = $this->orders->find($customer, $order);

        return $this->page($request, $this->returns->list($this->tenantId(), $customer, $model->uuid, $request->perPage()));
    }

    public function show(Request $request, int $return): JsonResponse
    {
        $this->cartContext($request);
        $model = $this->returns->find($this->tenantId(), $this->requireCustomer($request), $return);

        return JsonResponder::success('', ReturnResource::detail($model, $this->reasons())->toArray($request));
    }

    public function reply(ReturnReplyRequest $request, int $return): JsonResponse
    {
        $this->cartContext($request);
        $model = $this->returns->reply($this->tenantId(), $this->requireCustomer($request), $return, $request->toData());

        return JsonResponder::success((string) __('after-sales::messages.store_api.replied'), ReturnResource::detail($model, $this->reasons())->toArray($request));
    }

    public function withdraw(WithdrawReturnRequest $request, int $return): JsonResponse
    {
        $this->cartContext($request);
        $model = $this->returns->withdraw($this->tenantId(), $this->requireCustomer($request), $return, $request->note());

        return JsonResponder::success((string) __('after-sales::messages.store_api.withdrawn'), ReturnResource::detail($model, $this->reasons())->toArray($request));
    }

    /**
     * Evidence file (temporary signed URL from the return detail).
     */
    public function media(int $return, int $media): StreamedResponse
    {
        $file = $this->returns->media($this->tenantId(), $return, $media);

        return Storage::disk($file->disk)->response($file->path, $file->original_name);
    }

    /**
     * @param  LengthAwarePaginator<int, ReturnRequest>  $page
     */
    private function page(Request $request, LengthAwarePaginator $page): JsonResponse
    {
        $reasons = $this->reasons();

        return JsonResponder::success('', [
            'returns' => collect($page->items())->map(static fn (ReturnRequest $return): array => ReturnResource::row($return, $reasons)->toArray($request))->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    /**
     * @return array<string, ReturnReason>
     */
    private function reasons(): array
    {
        return $this->policies->reasons()->keyBy('code')->all();
    }

    private function tenantId(): int
    {
        return (int) tenant()?->getTenantKey();
    }
}
