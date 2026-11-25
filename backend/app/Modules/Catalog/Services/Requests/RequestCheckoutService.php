<?php

namespace App\Modules\Catalog\Services\Requests;

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Services\InvoiceCheckoutService;
use App\Modules\Catalog\Contracts\ThreadedRequest;
use App\Modules\Catalog\Enums\PaymentRequestStatus;
use App\Modules\Catalog\Enums\RequestKind;
use App\Modules\Catalog\Exceptions\RequestRuleException;
use App\Modules\Catalog\Models\Requests\RequestPayment;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Modules\Integrations\Models\PaymentGateway;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The store pays a payment request of its manufacturing / brand request (VEN-050f / 050i
 * → 050j): its Billing invoice goes through the hosted checkout (InvoiceCheckoutService).
 * The capture marks the invoice paid and InvoicePaid settles the payment request
 * (SettleRequestPayment), whichever of return URL or webhook arrives first.
 */
class RequestCheckoutService extends BaseService
{
    public function __construct(
        private readonly InvoiceCheckoutService $checkout,
        private readonly RequestPaymentService $payments,
        private readonly VendorRequestService $requests,
    ) {}

    /**
     * @return Collection<int, PaymentGateway>
     */
    public function gateways(): Collection
    {
        return $this->checkout->gateways();
    }

    public function findPayment(Model&ThreadedRequest $request, int $paymentId): RequestPayment
    {
        return $this->payments->find($request, $paymentId) ?? throw RequestRuleException::paymentNotPayable();
    }

    /**
     * @return array{log: PaymentLog, redirect: string}
     *
     * @throws RequestRuleException
     * @throws PaymentGatewayException
     */
    public function start(Model&ThreadedRequest $request, RequestPayment $payment, PaymentGatewayCode $gateway, ?string $email = null): array
    {
        $invoice = $payment->invoice;

        if ($payment->status !== PaymentRequestStatus::Pending || ! $invoice instanceof Invoice || $invoice->status !== InvoiceStatus::Unpaid) {
            throw RequestRuleException::paymentNotPayable();
        }

        return $this->checkout->start(
            $invoice,
            $gateway,
            $request->reference().' · '.$payment->label,
            static fn (PaymentLog $log): array => [
                'success' => vendor_route('requests.payment.result', ['payment' => $log->id]),
                'cancel' => vendor_route('requests.payment.cancel', ['payment' => $log->id]),
            ],
            $email,
            app()->getLocale(),
            ['request_payment_id' => $payment->id, 'request_kind' => $request->kind()->value, 'request_id' => (int) $request->getKey()],
        );
    }

    /**
     * The store's own request-payment log, or null.
     */
    public function log(int $logId): ?PaymentLog
    {
        $log = $this->checkout->logFor(VendorRequestService::tenantId(), $logId);

        return $log !== null && isset(($log->payload ?? [])['request_payment_id']) ? $log : null;
    }

    /**
     * @throws PaymentGatewayException
     */
    public function confirm(PaymentLog $log): PaymentLog
    {
        return $this->checkout->confirm($log);
    }

    public function cancel(PaymentLog $log): PaymentLog
    {
        return $this->checkout->cancel($log);
    }

    /**
     * The request a payment log was started from (still limited to the current store).
     */
    public function requestOf(PaymentLog $log): (Model&ThreadedRequest)|null
    {
        $payload = $log->payload ?? [];
        $kind = RequestKind::tryFrom((string) ($payload['request_kind'] ?? ''));

        if ($kind === null || ! is_numeric($payload['request_id'] ?? null)) {
            return null;
        }

        try {
            return $this->requests->findOwned($kind, (int) $payload['request_id']);
        } catch (NotFoundHttpException) {
            return null;
        }
    }
}
