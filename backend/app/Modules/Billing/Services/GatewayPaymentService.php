<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\DTOs\PaymentStatusChangeData;
use App\Modules\Billing\DTOs\StartPaymentData;
use App\Modules\Billing\Enums\PaymentGateway;
use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Enums\WebhookResult;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Repositories\Contracts\PaymentLogRepositoryContract;
use App\Modules\Integrations\DTOs\CheckoutRequestData;
use App\Modules\Integrations\DTOs\WebhookNotification;
use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Enums\PaymentOutcome;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Modules\Integrations\Payments\PaymentDriverRegistry;
use App\Shared\Services\BaseService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Hosted-checkout payments recorded in the payment log:
 *
 *   start()          pending log → driver checkout → gateway_reference = checkout id
 *   confirm()        return URL: ask the gateway (captures PayPal) and apply the outcome
 *   cancel()         cancel URL: re-check (a paid checkout is still captured), else failed
 *   handleWebhook()  signature-checked by the driver; replays are dropped (event id) and
 *                    status changes are idempotent under a row lock
 *
 * Captured / failed go through PaymentLogService::changeStatus(), which fires
 * PaymentCaptured / PaymentFailed; each purpose (registration, renewal…) reacts there.
 */
class GatewayPaymentService extends BaseService
{
    /** Webhook event ids remembered for replay protection. */
    public const WEBHOOK_REPLAY_TTL_DAYS = 7;

    public function __construct(
        private readonly PaymentLogRepositoryContract $logs,
        private readonly PaymentLogService $paymentLogs,
        private readonly PaymentDriverRegistry $drivers,
    ) {}

    public static function gatewayPurpose(PaymentPurpose $purpose): GatewayPurpose
    {
        return match ($purpose) {
            PaymentPurpose::Registration, PaymentPurpose::Renewal => GatewayPurpose::TenantRegistration,
            PaymentPurpose::Addon, PaymentPurpose::Settlement => GatewayPurpose::VendorPayments,
        };
    }

    /**
     * @param  Closure(PaymentLog): array{success: string, cancel: string}  $returnUrls
     * @return array{log: PaymentLog, redirect: string}
     *
     * @throws PaymentGatewayException
     */
    public function start(StartPaymentData $data, Closure $returnUrls): array
    {
        $purpose = self::gatewayPurpose($data->purpose);
        $gateway = $this->drivers->checkoutGateway($data->gateway, $purpose);
        $logGateway = PaymentGateway::tryFrom($data->gateway->value);

        if ($gateway === null || $logGateway === null) {
            throw PaymentGatewayException::unsupported();
        }

        $config = $this->drivers->config($data->gateway, $purpose);
        $log = $this->paymentLogs->record($data->tenantId, $logGateway, $data->purpose, $data->amount, payload: [
            ...$data->meta,
            'mode' => $config->mode->value,
            'fee_minor' => $gateway->feeFor($data->amount)->minor,
        ]);
        $urls = $returnUrls($log);

        try {
            $checkout = $this->drivers->driver($data->gateway)->createCheckout($config, new CheckoutRequestData(
                amount: $data->amount,
                reference: $log->reference(),
                description: $data->description,
                successUrl: $urls['success'],
                cancelUrl: $urls['cancel'],
                customerEmail: $data->customerEmail,
                locale: $data->locale,
            ));
        } catch (PaymentGatewayException $exception) {
            $this->paymentLogs->changeStatus($log, new PaymentStatusChangeData(PaymentLogStatus::Failed, $exception->getMessage()));

            throw $exception;
        }

        $this->logs->update($log, [
            'gateway_reference' => $checkout->id,
            'payload' => [...($log->payload ?? []), 'checkout_url' => $checkout->redirectUrl],
        ]);

        return ['log' => $log, 'redirect' => $checkout->redirectUrl];
    }

    /**
     * @throws PaymentGatewayException
     */
    public function confirm(PaymentLog $log): PaymentLog
    {
        if ($log->status === PaymentLogStatus::Captured || $log->gateway_reference === null) {
            return $log;
        }

        $code = $this->codeOf($log);
        $result = $this->drivers->driver($code)->verify($this->drivers->config($code, self::gatewayPurpose($log->purpose)), $log->gateway_reference);

        return $this->apply($log, $result->outcome, $result->reason, array_filter([
            'transaction_id' => $result->transactionId,
            'verification' => $result->details,
        ]));
    }

    /**
     * The payer came back through the cancel URL. A checkout that was paid after all
     * (another tab, a late redirect) is still captured.
     */
    public function cancel(PaymentLog $log): PaymentLog
    {
        if ($log->status !== PaymentLogStatus::Pending) {
            return $log;
        }

        try {
            $log = $this->confirm($log);
        } catch (PaymentGatewayException) {
            // Gateway unreachable: the payer cancelled, the webhook settles a late payment.
        }

        return $log->status === PaymentLogStatus::Pending
            ? $this->apply($log, PaymentOutcome::Cancelled, null)
            : $log;
    }

    /**
     * @throws PaymentGatewayException invalid signature (the caller answers 400)
     */
    public function handleWebhook(PaymentGatewayCode $code, Request $request): WebhookResult
    {
        $notification = $this->parse($code, $request);

        if (! $notification->isRelevant()) {
            return WebhookResult::Ignored;
        }

        $logGateway = PaymentGateway::tryFrom($code->value);
        $log = $logGateway === null ? null : $this->logs->findByGatewayReference($logGateway, (string) $notification->checkoutId);

        if ($log === null) {
            return WebhookResult::Ignored;
        }

        if (! Cache::add('payment-webhook:'.$code->value.':'.hash('sha256', $notification->eventId), true, now()->addDays(self::WEBHOOK_REPLAY_TTL_DAYS))) {
            return WebhookResult::Duplicate;
        }

        if ($notification->needsConfirmation) {
            $this->confirm($log);
        } elseif ($notification->outcome->isFinal()) {
            $this->apply($log, $notification->outcome, $notification->reason, ['webhook_event' => $notification->type]);
        }

        return WebhookResult::Processed;
    }

    /**
     * Apply a gateway outcome once: paid → captured (a failed log is reopened first),
     * failed / cancelled → failed. Anything already settled is left alone.
     *
     * @param  array<string, mixed>  $payload
     */
    public function apply(PaymentLog $log, PaymentOutcome $outcome, ?string $reason, array $payload = []): PaymentLog
    {
        return $this->transaction(function () use ($log, $outcome, $reason, $payload): PaymentLog {
            $locked = $this->logs->lockForUpdate($log);

            if (! $outcome->isFinal() || $locked->status === PaymentLogStatus::Captured || $locked->status === PaymentLogStatus::Refunded) {
                return $locked;
            }

            if ($payload !== [] || $reason !== null) {
                $this->logs->update($locked, ['payload' => array_filter([...($locked->payload ?? []), ...$payload, 'failure_reason' => $reason], static fn (mixed $value): bool => $value !== null)]);
            }

            if ($outcome === PaymentOutcome::Paid) {
                if ($locked->status === PaymentLogStatus::Failed) {
                    $this->paymentLogs->changeStatus($locked, new PaymentStatusChangeData(PaymentLogStatus::Pending));
                }

                return $this->paymentLogs->changeStatus($locked, new PaymentStatusChangeData(PaymentLogStatus::Captured));
            }

            if ($locked->status === PaymentLogStatus::Failed) {
                return $locked;
            }

            return $this->paymentLogs->changeStatus($locked, new PaymentStatusChangeData(PaymentLogStatus::Failed, $reason ?? ($outcome === PaymentOutcome::Cancelled ? 'cancelled' : null)));
        }, (new PaymentLog)->getConnectionName());
    }

    private function parse(PaymentGatewayCode $code, Request $request): WebhookNotification
    {
        $driver = $this->drivers->driver($code);
        $lastError = PaymentGatewayException::invalidSignature();

        // One endpoint per gateway: either purpose's webhook secret may have signed it.
        foreach ([GatewayPurpose::TenantRegistration, GatewayPurpose::VendorPayments] as $purpose) {
            try {
                return $driver->parseWebhook($this->drivers->config($code, $purpose), $request);
            } catch (PaymentGatewayException $exception) {
                $lastError = $exception;
            }
        }

        throw $lastError;
    }

    private function codeOf(PaymentLog $log): PaymentGatewayCode
    {
        $code = PaymentGatewayCode::tryFrom($log->gateway->value);

        if ($code === null) {
            throw PaymentGatewayException::unsupported();
        }

        return $code;
    }
}
