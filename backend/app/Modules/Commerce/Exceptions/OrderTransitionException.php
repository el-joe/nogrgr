<?php

namespace App\Modules\Commerce\Exceptions;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use LogicException;

/**
 * A status change the order state machine does not allow.
 */
final class OrderTransitionException extends LogicException
{
    public function __construct(public readonly OrderStatus $from, public readonly OrderStatus $to)
    {
        parent::__construct((string) __('commerce::orders.errors.transition', ['from' => $from->label(), 'to' => $to->label()]));
    }

    public function render(): JsonResponse
    {
        return JsonResponder::error($this->getMessage(), 422, ['reason' => 'invalid_transition']);
    }
}
