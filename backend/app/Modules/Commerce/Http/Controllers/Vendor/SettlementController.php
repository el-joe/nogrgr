<?php

namespace App\Modules\Commerce\Http\Controllers\Vendor;

use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Commerce\Http\Requests\Vendor\SettlePayRequest;
use App\Modules\Commerce\Services\Orders\VendorOrderService;
use App\Modules\Commerce\Services\Settlement\SettlementService;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * VEN-021d "Pay platform" (Figma 84:103244): settlement breakdown of a vendor-collected
 * order → choose a platform gateway → hosted checkout → result page (paid / processing /
 * cancelled with Try again). The capture (return URL or webhook) marks it paid once.
 */
class SettlementController extends Controller
{
    public function __construct(
        private readonly SettlementService $settlements,
        private readonly VendorOrderService $orders,
    ) {}

    public function show(string $order): View
    {
        $row = $this->orders->find($order);

        return view('commerce::vendor.settle.show', [
            'order' => $row,
            'gateways' => $this->settlements->gateways(),
        ] + $this->settlements->breakdown($row, $this->orders->tenantId()));
    }

    public function pay(SettlePayRequest $request, string $order): RedirectResponse
    {
        $row = $this->orders->find($order);
        $staff = $this->staff($request);

        try {
            $started = $this->settlements->start($row, $this->orders->tenantId(), $request->gateway(), static fn (PaymentLog $log): array => [
                'success' => vendor_route('finance.settle.result', ['payment' => $log->id]),
                'cancel' => vendor_route('finance.settle.cancel', ['payment' => $log->id]),
            ], $staff->id, $staff->email, app()->getLocale());
        } catch (PaymentGatewayException $exception) {
            return redirect()->to(vendor_route('finance.settle.show', ['order' => $row->uuid]))->with('error', $exception->getMessage());
        }

        return redirect()->away($started['redirect']);
    }

    public function result(int $payment): View
    {
        $log = $this->log($payment);

        if ($log->status === PaymentLogStatus::Pending) {
            $log = $this->settlements->confirm($log);
        }

        return $this->page($log, false);
    }

    public function cancel(int $payment): View
    {
        return $this->page($this->settlements->cancel($this->log($payment)), true);
    }

    private function page(PaymentLog $log, bool $cancelled): View
    {
        $settlement = $this->settlements->settlementOf($log);
        abort_if($settlement === null, 404);

        return view('commerce::vendor.settle.result', [
            'log' => $log,
            'settlement' => $settlement,
            'returnedFromCancel' => $cancelled,
        ]);
    }

    private function log(int $payment): PaymentLog
    {
        return $this->settlements->logFor($this->orders->tenantId(), $payment) ?? abort(404);
    }

    private function staff(Request $request): VendorStaff
    {
        /** @var VendorStaff $staff */
        $staff = $request->user('vendor');

        return $staff;
    }
}
