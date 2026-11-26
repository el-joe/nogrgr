<?php

namespace App\Modules\Storefront\Services\Account;

use App\Modules\AfterSales\Enums\ReturnStatus;
use App\Modules\AfterSales\Http\Resources\StoreApi\ReturnOptionsResource;
use App\Modules\AfterSales\Http\Resources\StoreApi\ReturnResource;
use App\Modules\AfterSales\Models\ReturnReason;
use App\Modules\AfterSales\Models\ReturnRequest;
use App\Modules\AfterSales\Services\CustomerReturnService;
use App\Modules\AfterSales\Services\ReturnPolicyService;
use App\Modules\Commerce\Services\Orders\CustomerOrderService;
use App\Modules\Commerce\Support\MoneyPresenter;
use App\Modules\Identity\Models\Customer;
use App\Modules\Storefront\Services\StorefrontContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Throwable;

/**
 * View data of the storefront return pages (SF-ECO-030/031/032): the Store API customer
 * return shapes (ReturnResource / ReturnOptionsResource) plus the line image and the
 * status chip group. Creating, replying and withdrawing go through the Store API.
 */
class AccountReturnPages
{
    /**
     * "My returns" chips → return statuses (filtered in the page, one customer's list).
     *
     * @var array<string, list<string>>
     */
    public const GROUPS = [
        'open' => ['pending', 'awaiting_info'],
        'approved' => ['approved', 'item_received', 'inspected', 'exchange_shipped', 'exchanged'],
        'refunded' => ['refunded'],
        'rejected' => ['rejected', 'closed'],
        'withdrawn' => ['cancelled'],
    ];

    public function __construct(
        private readonly CustomerReturnService $returns,
        private readonly ReturnPolicyService $policies,
        private readonly CustomerOrderService $orders,
        private readonly MoneyPresenter $money,
        private readonly StorefrontContext $context,
    ) {}

    public static function group(string $status): string
    {
        foreach (self::GROUPS as $group => $statuses) {
            if (in_array($status, $statuses, true)) {
                return $group;
            }
        }

        return 'open';
    }

    public static function tone(string $status): string
    {
        return match (ReturnStatus::tryFrom($status)) {
            ReturnStatus::Pending, ReturnStatus::AwaitingInfo => 'warning',
            ReturnStatus::Refunded, ReturnStatus::Exchanged => 'success',
            ReturnStatus::Rejected, ReturnStatus::Cancelled => 'danger',
            default => 'info',
        };
    }

    /**
     * @return array{page: LengthAwarePaginator<int, ReturnRequest>, rows: list<array<string, mixed>>}
     */
    public function list(Customer $customer, Request $request, int $perPage = 50): array
    {
        $this->useDisplayCurrency();
        $page = $this->returns->list($this->tenantId(), $customer, null, $perPage);
        $reasons = $this->reasons();
        $images = $this->images($customer, collect($page->items())->pluck('order_uuid')->unique()->values()->all());

        return [
            'page' => $page,
            'rows' => collect($page->items())->map(fn (ReturnRequest $return): array => ReturnResource::row($return, $reasons)->toArray($request) + [
                'group' => self::group($return->status->value),
                'tone' => self::tone($return->status->value),
                'image_url' => $images[$return->order_item_id] ?? null,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Customer $customer, Request $request, int $id): array
    {
        $this->useDisplayCurrency();
        $return = $this->returns->find($this->tenantId(), $customer, $id);
        $data = ReturnResource::detail($return, $this->reasons())->toArray($request);

        return $data + [
            'tone' => self::tone($return->status->value),
            'image_url' => $this->images($customer, [$return->order_uuid])[$return->order_item_id] ?? null,
            'resolution_method' => $return->type->label(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function options(Customer $customer, Request $request, string $orderUuid): array
    {
        $this->useDisplayCurrency();
        $order = $this->orders->find($customer, $orderUuid);

        return (new ReturnOptionsResource($this->returns->options($this->tenantId(), $order), $order))->toArray($request);
    }

    /**
     * Order line images (returns keep the product name, not the picture).
     *
     * @param  list<string>  $orderUuids
     * @return array<int, string|null>
     */
    private function images(Customer $customer, array $orderUuids): array
    {
        $images = [];

        foreach ($orderUuids as $uuid) {
            try {
                foreach ($this->orders->find($customer, (string) $uuid)->items as $item) {
                    $images[$item->id] = $item->image_url;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $images;
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

    private function useDisplayCurrency(): void
    {
        $this->money->useDisplayCurrency($this->context->currencyCode());
    }
}
