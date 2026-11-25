<?php

namespace App\Modules\Billing\Http\Controllers\Central;

use App\Modules\Billing\Services\GatewayPaymentService;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gateway webhooks: POST {central}/webhooks/payments/{gateway}. CSRF-exempt and
 * throttled; the driver checks the signature (400 when it fails). Replays and events
 * about unknown checkouts answer 200 so the gateway stops retrying.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(private readonly GatewayPaymentService $payments) {}

    public function __invoke(Request $request, string $gateway): JsonResponse
    {
        $code = PaymentGatewayCode::tryFrom($gateway);

        if ($code === null) {
            return response()->json(['received' => false], 404);
        }

        try {
            $result = $this->payments->handleWebhook($code, $request);
        } catch (PaymentGatewayException $exception) {
            return response()->json(['received' => false, 'error' => $exception->reason], $exception->reason === 'unsupported' ? 404 : 400);
        }

        return response()->json(['received' => true, 'result' => $result->value]);
    }
}
