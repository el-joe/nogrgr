<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Manufacturing request pipeline (Figma stepper): requested → quoted → sampling →
 * awaiting_payment → in_production → delivered; cancelled / rejected end it early.
 */
enum ManufacturingRequestStatus: string
{
    use HasEnumHelpers;

    case Requested = 'requested';
    case Quoted = 'quoted';
    case Sampling = 'sampling';
    case AwaitingPayment = 'awaiting_payment';
    case InProduction = 'in_production';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';

    public function tone(): string
    {
        return match ($this) {
            self::Requested => 'neutral',
            self::Quoted, self::Sampling, self::InProduction => 'info',
            self::AwaitingPayment => 'warning',
            self::Delivered => 'success',
            self::Cancelled, self::Rejected => 'danger',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Delivered, self::Cancelled, self::Rejected], true);
    }

    /**
     * The store may still cancel its request (nothing is in production yet).
     */
    public function isCancellableByVendor(): bool
    {
        return in_array($this, [self::Requested, self::Quoted, self::Sampling, self::AwaitingPayment], true);
    }

    /**
     * Pipeline steps shown in the stepper (terminal "stopped" states excluded).
     *
     * @return list<self>
     */
    public static function steps(): array
    {
        return [self::Requested, self::Quoted, self::Sampling, self::AwaitingPayment, self::InProduction, self::Delivered];
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return array_values(array_map(static fn (self $status): string => $status->value, array_filter(self::cases(), static fn (self $status): bool => $status->isOpen())));
    }
}
