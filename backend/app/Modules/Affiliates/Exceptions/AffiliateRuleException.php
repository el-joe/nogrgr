<?php

namespace App\Modules\Affiliates\Exceptions;

use App\Shared\Http\JsonResponder;
use App\Shared\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A rule of the affiliate program was broken: 422 JSON for AJAX forms, a flash error
 * for classic requests.
 */
class AffiliateRuleException extends RuntimeException
{
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return JsonResponder::error($this->getMessage(), 422);
        }

        return back()->with('error', $this->getMessage());
    }

    public static function amountAboveBalance(Money $balance): self
    {
        return new self(__('affiliates::admin.errors.above_balance', ['balance' => $balance->format()]));
    }

    public static function belowMinimum(Money $minimum): self
    {
        return new self(__('affiliates::admin.errors.below_minimum', ['minimum' => $minimum->format()]));
    }

    public static function notPositive(): self
    {
        return new self(__('affiliates::admin.errors.not_positive'));
    }

    public static function requestClosed(): self
    {
        return new self(__('affiliates::admin.errors.request_closed'));
    }

    public static function cannotReverse(): self
    {
        return new self(__('affiliates::admin.errors.cannot_reverse'));
    }

    public static function cannotReject(): self
    {
        return new self(__('affiliates::admin.errors.cannot_reject'));
    }

    public static function promoCodeInUse(): self
    {
        return new self(__('affiliates::admin.errors.promo_in_use'));
    }
}
