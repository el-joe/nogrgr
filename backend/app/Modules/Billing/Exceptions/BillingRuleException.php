<?php

namespace App\Modules\Billing\Exceptions;

use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A billing business rule refused the action (plan in use, invalid status change…).
 * Renders the JSON contract {success: false, message} with 422, or redirects back with
 * the message for classic requests.
 */
class BillingRuleException extends RuntimeException
{
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return JsonResponder::error($this->getMessage(), 422);
        }

        return back()->with('error', $this->getMessage());
    }

    public static function planInUse(int $subscriptions): self
    {
        return new self(trans_choice('billing::plans.in_use', $subscriptions, ['count' => $subscriptions]));
    }

    public static function planHasHistory(): self
    {
        return new self(__('billing::plans.has_history'));
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self(__('billing::messages.invalid_transition', ['from' => $from, 'to' => $to]));
    }
}
