<?php

namespace App\Modules\Billing\Http\Controllers\Owner;

use App\Modules\Billing\DataTables\OwnerSubscriptionsDataTable;
use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Exceptions\BillingRuleException;
use App\Modules\Billing\Http\Requests\Owner\RenewSubscriptionRequest;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\OwnerBillingService;
use App\Modules\Billing\Services\SubscriptionRenewalService;
use App\Modules\Identity\Models\Owner;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Owner portal › Subscriptions (OWN-003, Figma Affiliate & Owner 122:102615): every
 * store's plan in one list, a past-due alert, and Renew → hosted checkout (Stripe /
 * PayPal) → return page that confirms the payment and renews the subscription.
 */
class OwnerSubscriptionController extends Controller
{
    public function __construct(
        private readonly OwnerBillingService $billing,
        private readonly SubscriptionRenewalService $renewals,
    ) {}

    public function index(Request $request): View
    {
        $owner = $this->owner($request);

        return view('billing::owner.subscriptions.index', [
            'stores' => $this->billing->storeOptions($owner),
            'statuses' => SubscriptionStatus::options(),
            'pastDue' => $this->billing->pastDue($owner),
            'gateways' => $this->renewals->gateways(),
        ]);
    }

    public function data(Request $request, OwnerSubscriptionsDataTable $table): JsonResponse
    {
        return $table->forOwner($this->owner($request)->id)->toJsonFromRequest($request, OwnerSubscriptionsDataTable::FILTERS);
    }

    public function renew(RenewSubscriptionRequest $request, Subscription $subscription): RedirectResponse
    {
        $this->authorizeFor($request, $subscription);

        try {
            $checkout = $this->renewals->start($subscription, $request->gateway(), $this->owner($request), fn (PaymentLog $log): array => [
                'success' => route('owner.subscriptions.payment.return', ['subscription' => $subscription->id, 'payment' => $log->id]),
                'cancel' => route('owner.subscriptions.payment.cancel', ['subscription' => $subscription->id, 'payment' => $log->id]),
            ]);
        } catch (BillingRuleException $exception) {
            return redirect()->route('owner.subscriptions.index')->with('error', __('billing::owner.subscriptions.not_renewable'));
        } catch (PaymentGatewayException $exception) {
            report($exception);

            return redirect()->route('owner.subscriptions.index')->with('error', __('billing::owner.subscriptions.gateway_error'));
        }

        return redirect()->away($checkout['redirect']);
    }

    /**
     * Gateway success URL: verify (captures PayPal) and show the outcome.
     */
    public function paymentReturn(Request $request, Subscription $subscription, int $payment): RedirectResponse
    {
        $log = $this->paymentLog($request, $subscription, $payment);

        try {
            $log = $this->renewals->confirm($log);
        } catch (PaymentGatewayException $exception) {
            report($exception);
        }

        return $this->result($log);
    }

    public function paymentCancel(Request $request, Subscription $subscription, int $payment): RedirectResponse
    {
        return $this->result($this->renewals->cancel($this->paymentLog($request, $subscription, $payment)));
    }

    private function result(PaymentLog $log): RedirectResponse
    {
        $redirect = redirect()->route('owner.subscriptions.index');

        return match ($log->status) {
            PaymentLogStatus::Captured => $redirect->with('success', __('billing::owner.subscriptions.renewed')),
            PaymentLogStatus::Pending => $redirect->with('info', __('billing::owner.subscriptions.payment_pending')),
            default => $redirect->with('error', __('billing::owner.subscriptions.payment_failed')),
        };
    }

    private function paymentLog(Request $request, Subscription $subscription, int $payment): PaymentLog
    {
        $this->authorizeFor($request, $subscription);
        $log = $this->renewals->logFor($subscription, $payment);

        abort_if($log === null, 404);

        return $log;
    }

    private function authorizeFor(Request $request, Subscription $subscription): void
    {
        abort_unless($this->owner($request)->can('owner.subscriptions.manage', $subscription), 403);
    }

    private function owner(Request $request): Owner
    {
        /** @var Owner $owner */
        $owner = $request->user('owner');

        return $owner;
    }
}
