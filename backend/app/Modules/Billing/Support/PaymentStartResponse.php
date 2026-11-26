<?php

namespace App\Modules\Billing\Support;

use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;

/**
 * JSON answer of a payment start made by the shared Stripe card module
 * (resources/js/core/payments/stripe-card.js, decision #78 b):
 *   inline Stripe → data.inline = {client_secret, publishable_key, return_url, cancel_url}:
 *                   the page confirms the card with Stripe.js, then opens return_url,
 *                   where the server verifies the PaymentIntent and settles;
 *   hosted        → redirect = the gateway page (PayPal) or the given fallback.
 */
final class PaymentStartResponse
{
    /**
     * @param  array{redirect?: string, client_secret?: ?string, publishable_key?: ?string, success?: string, cancel?: string}  $started
     */
    public static function json(array $started, ?string $fallback = null): JsonResponse
    {
        $secret = $started['client_secret'] ?? null;

        if (is_string($secret) && $secret !== '') {
            return JsonResponder::success('', ['inline' => [
                'client_secret' => $secret,
                'publishable_key' => $started['publishable_key'] ?? null,
                'return_url' => $started['success'] ?? null,
                'cancel_url' => $started['cancel'] ?? null,
            ]]);
        }

        $redirect = ($started['redirect'] ?? '') !== '' ? $started['redirect'] : $fallback;

        return JsonResponder::success('', null, $redirect);
    }
}
