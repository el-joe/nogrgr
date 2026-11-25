<?php

namespace App\Modules\Platform\Services;

use App\Modules\Billing\DTOs\StartPaymentData;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Repositories\Contracts\PaymentLogRepositoryContract;
use App\Modules\Billing\Services\GatewayPaymentService;
use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Modules\Integrations\Models\PaymentGateway;
use App\Modules\Integrations\Payments\PaymentDriverRegistry;
use App\Modules\Platform\Enums\RegistrationStatus;
use App\Modules\Platform\Exceptions\PlatformRuleException;
use App\Modules\Platform\Models\VendorRegistration;
use App\Shared\Services\BaseService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/**
 * Payment step of a paid sign-up (WEB-016c → WEB-019 / 019a / 019b): hosted checkout
 * through Billing's GatewayPaymentService with the registration id on the payment log.
 * The registration itself moves on in AdvanceRegistrationOnPayment (PaymentCaptured /
 * PaymentFailed), whichever of return URL or webhook arrives first.
 */
class RegistrationPaymentService extends BaseService
{
    /** Signed return / redirect URLs stay valid this long. */
    public const RETURN_URL_HOURS = 24;

    /** Query keys gateways append to the return URL (PayPal: token, PayerID). */
    public const GATEWAY_QUERY = ['token', 'PayerID', 'session_id'];

    public function __construct(
        private readonly GatewayPaymentService $payments,
        private readonly PaymentDriverRegistry $drivers,
        private readonly PaymentLogRepositoryContract $logs,
        private readonly RegistrationFlowService $flow,
    ) {}

    /**
     * Gateways offered on the payment step: active, registration config enabled, driver.
     *
     * @return Collection<int, PaymentGateway>
     */
    public function gateways(): Collection
    {
        return $this->drivers->checkoutGateways(GatewayPurpose::TenantRegistration);
    }

    public function awaitsPayment(VendorRegistration $registration): bool
    {
        return in_array($registration->status, [RegistrationStatus::PaymentPending, RegistrationStatus::PaymentFailed], true);
    }

    /**
     * @return array{log: PaymentLog, redirect: string}
     *
     * @throws PlatformRuleException
     * @throws PaymentGatewayException
     */
    public function start(VendorRegistration $registration, PaymentGatewayCode $code): array
    {
        $quote = $this->flow->quoteFor($registration);

        if (! $this->awaitsPayment($registration) || $quote === null || ! $quote->requiresPayment()) {
            throw PlatformRuleException::paymentUnavailable();
        }

        return $this->payments->start(new StartPaymentData(
            gateway: $code,
            purpose: PaymentPurpose::Registration,
            amount: $quote->total,
            description: __('platform::signup.payment.description', ['plan' => $quote->plan->name, 'term' => $quote->term->label(), 'app' => config('app.name')]),
            customerEmail: $registration->email,
            locale: $registration->locale,
            meta: ['registration_id' => $registration->id, 'plan_id' => $quote->plan->id, 'term' => $quote->term->value],
        ), fn (PaymentLog $log): array => [
            'success' => $this->signedUrl('central.register.payment.success', $log),
            'cancel' => $this->signedUrl('central.register.payment.cancel', $log),
        ]);
    }

    public function chargeUrl(PaymentLog $log): string
    {
        return $this->signedUrl('central.register.payment.charge', $log);
    }

    /**
     * The registration's payment log (and only that registration's).
     */
    public function logFor(VendorRegistration $registration, int $logId): ?PaymentLog
    {
        /** @var PaymentLog|null $log */
        $log = $this->logs->query()->withoutGlobalScopes()->whereKey($logId)->first();

        return $log !== null && (int) (($log->payload ?? [])['registration_id'] ?? 0) === $registration->id ? $log : null;
    }

    /**
     * @throws PaymentGatewayException
     */
    public function confirm(PaymentLog $log): PaymentLog
    {
        return $this->payments->confirm($log);
    }

    public function cancel(PaymentLog $log): PaymentLog
    {
        return $this->payments->cancel($log);
    }

    private function signedUrl(string $route, PaymentLog $log): string
    {
        $registrationId = (int) (($log->payload ?? [])['registration_id'] ?? 0);

        return URL::temporarySignedRoute($route, now()->addHours(self::RETURN_URL_HOURS), ['registration' => $registrationId, 'payment' => $log->id]);
    }
}
