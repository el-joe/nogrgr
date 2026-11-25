<?php

namespace App\Modules\AfterSales\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * The actions of the return detail page (one modal each) and the status they lead to.
 */
enum ReturnAction: string
{
    use HasEnumHelpers;

    case Approve = 'approve';
    case Reject = 'reject';
    case RequestInfo = 'request_info';
    case Resume = 'resume';
    case MarkReceived = 'mark_received';
    case Inspect = 'inspect';
    case Refund = 'refund';
    case ShipExchange = 'ship_exchange';
    case CompleteExchange = 'complete_exchange';
    case Close = 'close';

    public function target(ReturnStatus $from): ReturnStatus
    {
        return match ($this) {
            self::Approve => ReturnStatus::Approved,
            self::Reject => ReturnStatus::Rejected,
            self::RequestInfo => ReturnStatus::AwaitingInfo,
            self::Resume => ReturnStatus::Pending,
            self::MarkReceived => ReturnStatus::ItemReceived,
            self::Inspect => ReturnStatus::Inspected,
            self::Refund => ReturnStatus::Refunded,
            self::ShipExchange => ReturnStatus::ExchangeShipped,
            self::CompleteExchange => ReturnStatus::Exchanged,
            self::Close => ReturnStatus::Closed,
        };
    }

    /**
     * Actions available from a status (the state machine decides).
     *
     * @return list<self>
     */
    public static function availableFor(ReturnStatus $status, ReturnType $type): array
    {
        return array_values(array_filter(self::cases(), static function (self $action) use ($status, $type): bool {
            if ($action === self::ShipExchange && $type !== ReturnType::Exchange) {
                return false;
            }

            if ($action === self::Refund && $type === ReturnType::Exchange) {
                return false;
            }

            return $status->canTransitionTo($action->target($status));
        }));
    }
}
