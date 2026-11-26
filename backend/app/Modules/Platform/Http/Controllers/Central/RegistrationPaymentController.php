<?php

namespace App\Modules\Platform\Http\Controllers\Central;

use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Support\PaymentStartResponse;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Modules\Integrations\Payments\PaymentDriverRegistry;
use App\Modules\Platform\Exceptions\PlatformRuleException;
use App\Modules\Platform\Http\Requests\Central\StartRegistrationPaymentRequest;
use App\Modules\Platform\Models\VendorRegistration;
use App\Modules\Platform\Repositories\Contracts\VendorRegistrationRepositoryContract;
use App\Modules\Platform\Services\RegistrationFlowService;
use App\Modules\Platform\Services\RegistrationPaymentService;
use App\Modules\Platform\Support\RegistrationSession;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Paid sign-up:
 *   show     WEB-016c payment step (gateway + order summary; coupon hidden until P5)
 *   store    starts the hosted checkout → charge
 *   charge   WEB-019 "Redirecting to secure payment…" (signed)
 *   success  WEB-019a gateway return: verify / capture → paid, processing or failed
 *   cancel   WEB-019b failed / cancelled with "Try again" and "Change plan"
 * Return URLs are signed (gateway query keys ignored) and bound to the registration.
 */
class RegistrationPaymentController extends Controller
{
    public function __construct(
        private readonly RegistrationPaymentService $payments,
        private readonly RegistrationFlowService $flow,
        private readonly VendorRegistrationRepositoryContract $registrations,
        private readonly PaymentDriverRegistry $drivers,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $registration = $this->flow->current(new RegistrationSession($request->session()));

        if ($registration === null) {
            return redirect()->route('central.register');
        }

        if (! $this->payments->awaitsPayment($registration)) {
            return redirect()->route('central.register.verify');
        }

        return view('platform::central.signup.payment', [
            'registration' => $registration,
            'quote' => $this->flow->quoteFor($registration),
            'gateways' => $this->payments->gateways(),
        ]);
    }

    /**
     * Form post → hosted checkout via the Redirecting page; the shared Stripe card posts
     * as JSON (inline mode) and gets the PaymentIntent client secret instead (#78 b).
     */
    public function store(StartRegistrationPaymentRequest $request): RedirectResponse|JsonResponse
    {
        $inline = $request->expectsJson();
        $registration = $this->flow->current(new RegistrationSession($request->session()));

        if ($registration === null) {
            return $inline ? JsonResponder::error(__('platform::signup.payment.start_failed'), 409, redirect: route('central.register')) : redirect()->route('central.register');
        }

        $start = fn (): array => $this->payments->start($registration, $request->gateway());

        try {
            $payment = $inline ? $this->drivers->inline($start) : $start();
        } catch (PlatformRuleException $exception) {
            return $inline ? JsonResponder::error($exception->getMessage(), 422) : redirect()->route('central.register.payment')->with('error', $exception->getMessage());
        } catch (PaymentGatewayException $exception) {
            report($exception);

            return $inline
                ? JsonResponder::error(__('platform::signup.payment.start_failed'), 422)
                : redirect()->route('central.register.payment')->withInput()->with('error', __('platform::signup.payment.start_failed'));
        }

        $charge = $this->payments->chargeUrl($payment['log']);

        return $inline ? PaymentStartResponse::json($payment, $charge) : redirect()->to($charge);
    }

    public function charge(Request $request, int $registration, int $payment): View
    {
        [$model, $log] = $this->resolve($request, $registration, $payment);

        if ($model === null || $log === null) {
            return $this->invalid();
        }

        $checkoutUrl = ($log->payload ?? [])['checkout_url'] ?? null;

        if ($log->status !== PaymentLogStatus::Pending || ! is_string($checkoutUrl)) {
            return $this->result($model, $log, $request);
        }

        return view('platform::central.signup.payment-redirect', [
            'registration' => $model,
            'quote' => $this->flow->quoteFor($model),
            'checkoutUrl' => $checkoutUrl,
        ]);
    }

    public function success(Request $request, int $registration, int $payment): View
    {
        [$model, $log] = $this->resolve($request, $registration, $payment);

        if ($model === null || $log === null) {
            return $this->invalid();
        }

        try {
            $log = $this->payments->confirm($log);
        } catch (PaymentGatewayException $exception) {
            report($exception);
        }

        return $this->result($model, $log, $request);
    }

    public function cancel(Request $request, int $registration, int $payment): View
    {
        [$model, $log] = $this->resolve($request, $registration, $payment);

        if ($model === null || $log === null) {
            return $this->invalid();
        }

        return $this->result($model, $this->payments->cancel($log), $request);
    }

    private function result(VendorRegistration $registration, PaymentLog $log, Request $request): View
    {
        $registration->refresh();
        (new RegistrationSession($request->session()))->setCurrent($registration->id);
        $quote = $this->flow->quoteFor($registration);

        if ($log->status === PaymentLogStatus::Captured) {
            return view('platform::central.signup.payment-success', [
                'registration' => $registration,
                'quote' => $quote,
                'log' => $log,
            ]);
        }

        if ($log->status === PaymentLogStatus::Pending) {
            return view('platform::central.signup.payment-processing', [
                'registration' => $registration,
                'log' => $log,
                'refreshUrl' => $request->fullUrl(),
            ]);
        }

        $reason = ($log->payload ?? [])['failure_reason'] ?? null;

        return view('platform::central.signup.payment-failed', [
            'registration' => $registration,
            'cancelled' => in_array($reason, ['cancelled', null], true),
            'reason' => is_string($reason) && $reason !== 'cancelled' ? $reason : null,
        ]);
    }

    private function invalid(): View
    {
        return view('platform::central.signup.payment-failed', ['registration' => null, 'cancelled' => false, 'reason' => null, 'invalid' => true]);
    }

    /**
     * @return array{0: ?VendorRegistration, 1: ?PaymentLog}
     */
    private function resolve(Request $request, int $registrationId, int $paymentId): array
    {
        if (! $request->hasValidSignatureWhileIgnoring(RegistrationPaymentService::GATEWAY_QUERY)) {
            return [null, null];
        }

        /** @var VendorRegistration|null $registration */
        $registration = $this->registrations->find($registrationId);
        $log = $registration === null ? null : $this->payments->logFor($registration, $paymentId);

        return [$registration, $log];
    }
}
