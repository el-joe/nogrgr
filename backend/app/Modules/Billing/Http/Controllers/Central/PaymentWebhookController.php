<?php

namespace App\Modules\Billing\Http\Controllers\Central;

use App\Modules\Billing\Enums\WebhookResult;
use App\Modules\Billing\Services\GatewayPaymentService;
use App\Modules\Commerce\Services\Payments\CentralWebhookRouter;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gateway webhooks: POST {central}/webhooks/payments/{gateway}. CSRF-exempt and
 * throttled; the driver checks the signature (400 when it fails). Replays and events
 * about unknown checkouts answer 200 so the gateway stops retrying. Events Billing does
 * not know are offered to the storefront router (store checkouts on the platform's keys).
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly GatewayPaymentService $payments,
        private readonly CentralWebhookRouter $storefront,
    ) {}

    public function __invoke(Request $request, string $gateway): JsonResponse
    {
        $code = PaymentGatewayCode::tryFrom($gateway);

        if ($code === null) {
            return response()->json(['received' => false], 404);
        }

        try {
            $result = $this->payments->handleWebhook($code, $request);
        } catch (PaymentGatewayException $exception) {
            $routed = $this->storefront->route($code, $request);

            return $routed !== null
                ? response()->json(['received' => true, 'result' => $routed])
                : response()->json(['received' => false, 'error' => $exception->reason], $exception->reason === 'unsupported' ? 404 : 400);
        }

        if ($result === WebhookResult::Ignored) {
            $result = WebhookResult::tryFrom((string) $this->storefront->route($code, $request)) ?? $result;
        }

        return response()->json(['received' => true, 'result' => $result->value]);
    }
}
