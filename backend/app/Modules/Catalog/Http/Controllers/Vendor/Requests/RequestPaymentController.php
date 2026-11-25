<?php

namespace App\Modules\Catalog\Http\Controllers\Vendor\Requests;

use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Catalog\Enums\RequestKind;
use App\Modules\Catalog\Http\Requests\Vendor\Requests\RequestPayRequest;
use App\Modules\Catalog\Services\Requests\RequestCheckoutService;
use App\Modules\Catalog\Services\Requests\VendorRequestService;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Paying a payment request of a manufacturing / brand request (VEN-050f / 050i) through
 * the hosted checkout, and the result page (VEN-050j, Figma 87:110730): paid / still
 * processing / cancelled with "Try again". Settlement happens on capture (return URL or
 * webhook), never twice.
 */
class RequestPaymentController extends Controller
{
    public function __construct(
        private readonly RequestCheckoutService $checkout,
        private readonly VendorRequestService $requests,
    ) {}

    public function pay(RequestPayRequest $request, int $id, int $payment): RedirectResponse
    {
        $kind = RequestKind::fromVendorRouteName($request->route()?->getName()) ?? abort(404);
        $thread = $this->requests->findOwned($kind, $id);
        $row = $this->checkout->findPayment($thread, $payment);

        try {
            $started = $this->checkout->start($thread, $row, $request->gateway(), $this->staff($request)->email);
        } catch (PaymentGatewayException $exception) {
            return redirect()->to(vendor_route('requests.'.$kind->value.'.show', ['id' => $id]))->with('error', $exception->getMessage());
        }

        return redirect()->away($started['redirect']);
    }

    public function result(Request $request, int $payment): View
    {
        $log = $this->log($request, $payment);

        if ($log->status === PaymentLogStatus::Pending) {
            try {
                $log = $this->checkout->confirm($log);
            } catch (PaymentGatewayException) {
                // Gateway unreachable: the webhook settles it; the page shows "processing".
            }
        }

        return $this->page($log, false);
    }

    public function cancel(Request $request, int $payment): View
    {
        return $this->page($this->checkout->cancel($this->log($request, $payment)), true);
    }

    private function page(PaymentLog $log, bool $cancelled): View
    {
        $thread = $this->checkout->requestOf($log);
        abort_if($thread === null, 404);

        return view('catalog::vendor.requests.payment-result', [
            'log' => $log->loadMissing('invoice'),
            'request' => $thread,
            'payment' => $this->checkout->findPayment($thread, (int) (($log->payload ?? [])['request_payment_id'] ?? 0)),
            'returnedFromCancel' => $cancelled,
            'gateways' => $this->checkout->gateways(),
        ]);
    }

    private function log(Request $request, int $payment): PaymentLog
    {
        $log = $this->checkout->log($payment);
        abort_if($log === null, 404);

        $kind = RequestKind::tryFrom((string) (($log->payload ?? [])['request_kind'] ?? ''));
        abort_unless($kind !== null && $this->staff($request)->can($kind->permission('view')), 403);

        return $log;
    }

    private function staff(Request $request): VendorStaff
    {
        /** @var VendorStaff $staff */
        $staff = $request->user('vendor');

        return $staff;
    }
}
