<?php

namespace App\Modules\AfterSales\Services;

use App\Modules\AfterSales\DTOs\Actor;
use App\Modules\AfterSales\DTOs\CreateReturnData;
use App\Modules\AfterSales\DTOs\CustomerReturnData;
use App\Modules\AfterSales\DTOs\ReturnMessageData;
use App\Modules\AfterSales\Enums\ReturnBlock;
use App\Modules\AfterSales\Enums\ReturnEventType;
use App\Modules\AfterSales\Enums\ReturnMediaKind;
use App\Modules\AfterSales\Events\ReturnCustomerMessaged;
use App\Modules\AfterSales\Exceptions\AfterSalesException;
use App\Modules\AfterSales\Models\ReturnPolicy;
use App\Modules\AfterSales\Models\ReturnReason;
use App\Modules\AfterSales\Models\ReturnRequest;
use App\Modules\AfterSales\Models\ReturnRequestMedia;
use App\Modules\AfterSales\Repositories\Contracts\ReturnRequestRepositoryContract;
use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Catalog\Repositories\Contracts\CategoryRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\ProductRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\StoreProductRepositoryContract;
use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Models\OrderStatusHistory;
use App\Modules\Identity\Models\Customer;
use App\Shared\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * Customer side of returns (Store API, SF-ECO-030/031/032, APP-016/017/018). Adds the
 * customer rules of the platform policy (ADM-090) on top of ReturnRequestService:
 * the return window counted from delivery, non-returnable categories and the video
 * requirement (policy or reason). A customer only ever sees and acts on their own
 * returns of the current store; amounts come from ReturnRequestService (RefundCalculator).
 */
class CustomerReturnService extends BaseService
{
    public function __construct(
        private readonly ReturnRequestService $returns,
        private readonly ReturnPolicyService $policies,
        private readonly ReturnRequestRepositoryContract $repository,
        private readonly CategoryRepositoryContract $categories,
        private readonly ProductRepositoryContract $products,
        private readonly StoreProductRepositoryContract $storeProducts,
    ) {}

    /**
     * What the "request return" form needs: returnable lines, reasons, policy, deadline
     * and why the order cannot be returned (if so).
     *
     * @return array{policy: ReturnPolicy, reasons: list<ReturnReason>, deadline: Carbon|null, block: ReturnBlock|null, lines: list<array{item: OrderItem, available: int, returnable: bool}>}
     */
    public function options(int $tenantId, Order $order): array
    {
        $policy = $this->policies->policy();
        $available = $this->returns->returnableLines($tenantId, $order);
        $blocked = $this->blockedItemIds($order);
        $lines = [];

        foreach ($order->items as $item) {
            $left = $available[$item->id]['available'] ?? 0;
            $lines[] = ['item' => $item, 'available' => $left, 'returnable' => $left > 0 && ! in_array($item->id, $blocked, true)];
        }

        $block = $this->block($order, $policy);

        if ($block === null && ! in_array(true, array_column($lines, 'returnable'), true)) {
            $block = ReturnBlock::NothingReturnable;
        }

        return [
            'policy' => $policy,
            'reasons' => array_values($this->policies->reasons(true)->all()),
            'deadline' => $this->deadline($order, $policy),
            'block' => $block,
            'lines' => $lines,
        ];
    }

    /**
     * Opens one return per line (same reason, description and evidence).
     *
     * @return list<ReturnRequest>
     */
    public function create(int $tenantId, Customer $customer, Order $order, CustomerReturnData $data): array
    {
        $policy = $this->policies->policy();
        $block = $this->block($order, $policy);

        if ($block !== null) {
            throw AfterSalesException::rule($block->value);
        }

        $reason = $this->policies->reasonByCode($data->reasonCode);

        if ($reason === null || ! $reason->is_active) {
            throw AfterSalesException::rule('reason_invalid');
        }

        if (($policy->require_video || $reason->requires_video) && $data->video === null) {
            throw AfterSalesException::rule('video_required');
        }

        $available = $this->returns->returnableLines($tenantId, $order);
        $blocked = $this->blockedItemIds($order);

        foreach ($data->lines as $line) {
            if (in_array($line->orderItemId, $blocked, true)) {
                throw AfterSalesException::rule('category_not_returnable');
            }

            $left = $available[$line->orderItemId]['available'] ?? null;

            if ($left === null) {
                throw AfterSalesException::rule('line_not_returnable');
            }

            if ($line->quantity > $left) {
                throw AfterSalesException::rule('quantity_exceeds', 422, ['max' => $left]);
            }
        }

        $actor = $this->actor($customer);
        $created = [];

        foreach ($data->lines as $line) {
            $return = $this->returns->create($tenantId, $order, new CreateReturnData(
                orderItemId: $line->orderItemId,
                quantity: $line->quantity,
                reasonCode: $reason->code,
                description: $data->description,
                type: $data->type,
            ), $actor);

            $this->attach($return, $data->photos, $data->video, $actor);
            $created[] = $return;
        }

        return $created;
    }

    /**
     * @return LengthAwarePaginator<int, ReturnRequest>
     */
    public function list(int $tenantId, Customer $customer, ?string $orderUuid = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->forCustomer($tenantId, $customer->id, $orderUuid, $perPage);
    }

    public function find(int $tenantId, Customer $customer, int $id): ReturnRequest
    {
        return $this->repository->findForCustomer($tenantId, $customer->id, $id)
            ?? throw AfterSalesException::rule('return_not_found', 404);
    }

    public function reply(int $tenantId, Customer $customer, int $id, ReturnMessageData $data): ReturnRequest
    {
        $return = $this->find($tenantId, $customer, $id);

        if (! $return->status->isOpen()) {
            throw AfterSalesException::rule('reply_not_allowed', 422, ['status' => $return->status->label()]);
        }

        $actor = $this->actor($customer);

        $this->transaction(function () use ($return, $data, $actor): void {
            $this->repository->addEvent($return, [
                'type' => ReturnEventType::Message,
                'note' => $data->message,
                'customer_visible' => true,
                'actor_type' => $actor->type,
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
            ]);
        }, $return->getConnectionName());

        $this->attach($return, $data->photos, $data->video, $actor);
        ReturnCustomerMessaged::dispatch($return, $data->message);

        return $this->find($tenantId, $customer, $id);
    }

    public function withdraw(int $tenantId, Customer $customer, int $id, ?string $note = null): ReturnRequest
    {
        $return = $this->find($tenantId, $customer, $id);
        $this->returns->withdraw($return, $this->actor($customer), $note);

        return $this->find($tenantId, $customer, $id);
    }

    /**
     * A media file of a return of the current store (signed URL route).
     */
    public function media(int $tenantId, int $returnId, int $mediaId): ReturnRequestMedia
    {
        return $this->returns->media($this->returns->find($returnId, $tenantId), $mediaId);
    }

    /**
     * Last day to open a return: delivery (or the delivered history entry, completion,
     * last update) + window days. Null while the order is still on its way.
     */
    public function deadline(Order $order, ReturnPolicy $policy): ?Carbon
    {
        $delivered = $order->delivered_at
            ?? $order->history->first(static fn (OrderStatusHistory $entry): bool => $entry->to_status === OrderStatus::Delivered)->created_at
            ?? $order->completed_at
            ?? ($order->status === OrderStatus::Shipped ? null : $order->updated_at);

        return $delivered?->copy()->addDays($policy->window_days);
    }

    private function block(Order $order, ReturnPolicy $policy): ?ReturnBlock
    {
        if (! $this->returns->canOpenFor($order)) {
            return ReturnBlock::OrderNotReturnable;
        }

        $deadline = $this->deadline($order, $policy);

        return $deadline !== null && $deadline->isPast() ? ReturnBlock::WindowExpired : null;
    }

    /**
     * Order lines whose product sits in a non-returnable category (or one of its
     * sub-categories). Central products by their central categories, own products by
     * their store placement.
     *
     * @return list<int>
     */
    private function blockedItemIds(Order $order): array
    {
        $roots = array_map('intval', $this->policies->policy()->non_returnable_category_ids ?? []);

        if ($roots === []) {
            return [];
        }

        $categoryIds = $this->withDescendants($roots);
        $centralIds = $order->items->contains(static fn (OrderItem $item): bool => $item->product_source === StoreProductSource::Central)
            ? $this->products->idsInCategories($categoryIds)
            : [];
        $blocked = [];

        foreach ($order->items as $item) {
            $inCategory = $item->product_source === StoreProductSource::Central
                ? in_array($item->product_id, $centralIds, true)
                : array_intersect(array_map('intval', $this->storeProducts->findOwn($item->store_product_id)->category_ids ?? []), $categoryIds) !== [];

            if ($inCategory) {
                $blocked[] = $item->id;
            }
        }

        return $blocked;
    }

    /**
     * @param  list<int>  $roots
     * @return list<int>
     */
    private function withDescendants(array $roots): array
    {
        $parents = $this->categories->parentMap();
        $ids = $roots;

        do {
            $added = false;

            foreach ($parents as $id => $parent) {
                if ($parent !== null && in_array($parent, $ids, true) && ! in_array($id, $ids, true)) {
                    $ids[] = $id;
                    $added = true;
                }
            }
        } while ($added);

        return $ids;
    }

    /**
     * @param  list<UploadedFile>  $photos
     */
    private function attach(ReturnRequest $return, array $photos, ?UploadedFile $video, Actor $actor): void
    {
        if ($photos !== []) {
            $this->returns->addPhotos($return, $photos, ReturnMediaKind::Photo, $actor);
        }

        if ($video !== null) {
            $this->returns->addPhotos($return, [$video], ReturnMediaKind::Video, $actor);
        }
    }

    private function actor(Customer $customer): Actor
    {
        return new Actor(ActorType::Customer, $customer->id, $customer->name);
    }
}
