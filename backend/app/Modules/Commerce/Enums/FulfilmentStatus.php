<?php

namespace App\Modules\Commerce\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Shipping progress of an order (orders.fulfilment_status), next to OrderStatus:
 *
 *   pending → packed → in_transit → out_for_delivery → delivered
 *                      in_transit | out_for_delivery ⇄ delayed → delivered
 *
 * packed moves a paid order to processing, in_transit ships it (branch stock of the
 * central lines is deducted), delivered delivers it (OrderStatus side effects).
 */
enum FulfilmentStatus: string
{
    use HasEnumHelpers;

    case Pending = 'pending';
    case Packed = 'packed';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delayed = 'delayed';
    case Delivered = 'delivered';

    /**
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Pending => [self::Packed, self::InTransit],
            self::Packed => [self::InTransit],
            self::InTransit => [self::OutForDelivery, self::Delayed, self::Delivered],
            self::OutForDelivery => [self::Delayed, self::Delivered],
            self::Delayed => [self::InTransit, self::OutForDelivery, self::Delivered],
            self::Delivered => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->transitions(), true);
    }

    /**
     * The order status this shipping step requires (null = unchanged).
     */
    public function orderStatus(): ?OrderStatus
    {
        return match ($this) {
            self::Packed => OrderStatus::Processing,
            self::InTransit => OrderStatus::Shipped,
            self::Delivered => OrderStatus::Delivered,
            default => null,
        };
    }

    /**
     * Shipping queue tab of the status.
     */
    public function queueTab(): string
    {
        return match ($this) {
            self::Pending, self::Packed => 'to_ship',
            self::InTransit, self::OutForDelivery => 'in_transit',
            self::Delayed => 'delayed',
            self::Delivered => 'delivered',
        };
    }

    /**
     * @return list<string>
     */
    public static function valuesForTab(string $tab): array
    {
        return array_values(array_map(
            static fn (self $status): string => $status->value,
            array_filter(self::cases(), static fn (self $status): bool => $status->queueTab() === $tab),
        ));
    }
}
