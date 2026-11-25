<?php

namespace App\Modules\Commerce\Services\Orders;

use App\Modules\Commerce\DTOs\Orders\FulfilmentUpdateData;
use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Enums\FulfilmentStatus;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Events\OrderFulfilmentUpdated;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Repositories\Contracts\OrderRepositoryContract;
use App\Modules\Commerce\Services\Stock\FulfilmentStockService;
use App\Modules\Commerce\Support\TenantRunner;
use App\Modules\Shipping\DTOs\DeliveryEstimate;
use App\Modules\Shipping\Services\DeliveryEstimateService;
use App\Modules\Shipping\Services\ShippingCompanyService;
use App\Shared\Services\BaseService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Shipping progress of an order (tenant context). FulfilmentStatus decides the allowed
 * steps; the steps that change the order status go through OrderLifecycleService (paid
 * → processing → shipped → delivered, with its events and notifications). Shipping a
 * catalog order deducts the branch stock of its central lines. Platform staff may not
 * touch own-products orders (merchant fulfilled); store staff may update only those.
 */
class OrderFulfilmentService extends BaseService
{
    public function __construct(
        private readonly OrderRepositoryContract $orders,
        private readonly OrderLifecycleService $lifecycle,
        private readonly FulfilmentStockService $stock,
        private readonly ShippingCompanyService $companies,
        private readonly DeliveryEstimateService $estimates,
        private readonly TenantRunner $tenants,
    ) {}

    public function assertPlatformManageable(Order $order): void
    {
        if ($order->isOwnProducts()) {
            throw CommerceException::rule('own_products_read_only', 403);
        }
    }

    public function assertStoreManageable(Order $order): void
    {
        if (! $order->isOwnProducts()) {
            throw CommerceException::rule('platform_fulfilled', 403);
        }
    }

    public function update(Order $order, FulfilmentUpdateData $data): Order
    {
        $from = $order->fulfilment_status;
        $to = $data->status;

        if (! in_array($order->status, [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped], true)) {
            throw CommerceException::rule('not_fulfillable');
        }

        if ($from !== $to && ! $from->canTransitionTo($to)) {
            throw CommerceException::rule('fulfilment_transition');
        }

        if ($data->shippingCompanyId !== null) {
            $this->applyCompany($order, $data->shippingCompanyId, false);
        }

        if ($data->trackingNumber !== null && $data->trackingNumber !== '') {
            $order->tracking_number = $data->trackingNumber;
        }

        if ($to === FulfilmentStatus::InTransit && $from !== FulfilmentStatus::InTransit && $order->fulfilment_branch_id === null && ! $order->isOwnProducts()) {
            $order->fulfilment_branch_id = $this->stock->shipOrder($order, $data->branchId);
        }

        $order->fulfilment_status = $to;
        $order->save();
        $this->orders->recordTrackingEvent($order, $to, $data->actor, $data->actorId, $data->location, $data->note);

        $target = $to->orderStatus();

        if ($target !== null && $order->status !== $target) {
            $this->moveOrderTo($order, $target, $data->actor, $data->actorId, $data->note);
        } else {
            OrderFulfilmentUpdated::dispatch($order, $this->tenantId(), $to, $data->notifyCustomer);
        }

        return $order;
    }

    /**
     * ADM-021d: another carrier; the delivery window is recomputed from the order date
     * with the new company's day rules and stop periods.
     */
    public function reassignCompany(Order $order, int $companyId, ActorType $actor, ?int $actorId = null): DeliveryEstimate
    {
        $estimate = $this->applyCompany($order, $companyId, true);
        $order->save();
        $this->orders->recordTrackingEvent($order, $order->fulfilment_status, $actor, $actorId, null, (string) __('commerce::fulfilment.notes.reassigned', ['company' => (string) $order->shipping_company_name]));
        OrderFulfilmentUpdated::dispatch($order, $this->tenantId(), $order->fulfilment_status, false);

        return $estimate;
    }

    public function previewEstimate(Order $order, int $companyId): ?DeliveryEstimate
    {
        if ($order->country_id === null) {
            return null;
        }

        return $this->estimates->estimateFor($order->country_id, $companyId, CarbonImmutable::instance($order->placed_at ?? $order->created_at ?? now()));
    }

    private function applyCompany(Order $order, int $companyId, bool $reestimate): ?DeliveryEstimate
    {
        $name = $this->companies->activeOptions()[$companyId] ?? null;

        if ($name === null) {
            throw CommerceException::rule('company_unavailable', 422);
        }

        $order->shipping_company_id = $companyId;
        $order->shipping_company_name = mb_substr((string) $name, 0, 120);

        if (! $reestimate) {
            return null;
        }

        $estimate = $this->previewEstimate($order, $companyId);

        if ($estimate !== null) {
            $order->delivery_min_date = Carbon::instance($estimate->minDate);
            $order->delivery_max_date = Carbon::instance($estimate->maxDate);
            $order->delivery_estimate = $estimate->toArray();
        }

        return $estimate ?? throw CommerceException::rule('no_destination', 422);
    }

    private function moveOrderTo(Order $order, OrderStatus $target, ActorType $actor, ?int $actorId, ?string $note): void
    {
        if ($target === OrderStatus::Shipped && $order->status === OrderStatus::Paid) {
            $this->lifecycle->transition($order, OrderStatus::Processing, $actor, $actorId);
        }

        if ($target === OrderStatus::Delivered && in_array($order->status, [OrderStatus::Paid, OrderStatus::Processing], true)) {
            $this->moveOrderTo($order, OrderStatus::Shipped, $actor, $actorId, null);
        }

        if ($order->status !== $target) {
            $this->lifecycle->transition($order, $target, $actor, $actorId, $note);
        }
    }

    private function tenantId(): int
    {
        return (int) $this->tenants->currentTenantId();
    }
}
