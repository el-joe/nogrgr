<?php

namespace App\Modules\Commerce\Http\Controllers\Vendor;

use App\Modules\Commerce\DTOs\Payments\StoreGatewayRow;
use App\Modules\Commerce\Http\Requests\Vendor\StoreGatewayRequest;
use App\Modules\Commerce\Services\Payments\StoreGatewaySettingsService;
use App\Modules\Integrations\Enums\GatewayMode;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * Vendor › Settings › Payment gateways (VEN-078 / 078a, Figma 90:116465): the gateways
 * the platform offers, the selected gateway's editor (own merchant keys masked, sandbox /
 * live, check connection, save), set primary, and the payment readiness checklist.
 * Credentials come only from the database (store_payment_gateways, encrypted).
 */
class PaymentGatewayController extends Controller
{
    public function __construct(private readonly StoreGatewaySettingsService $gateways) {}

    public function index(): View
    {
        $list = $this->gateways->list();

        return $this->page($list, $list[0] ?? null);
    }

    public function show(string $code): View
    {
        $item = $this->gateways->find(PaymentGatewayCode::tryFrom($code) ?? abort(404));

        return $this->page($this->gateways->list(), $item);
    }

    public function update(StoreGatewayRequest $request, string $code): JsonResponse
    {
        $row = $this->gateways->update($request->code(), $request->toData());

        return JsonResponder::success(__('commerce::gateways.saved', ['name' => $row->code->brand()]), redirect: vendor_route('settings.payment-gateways.show', ['code' => $row->code->value]));
    }

    public function test(string $code): JsonResponse
    {
        $gateway = PaymentGatewayCode::tryFrom($code) ?? abort(404);
        $result = $this->gateways->test($gateway);

        return $result->ok
            ? JsonResponder::success(__('commerce::gateways.test_ok', ['name' => $gateway->brand()]), ['ok' => true], vendor_route('settings.payment-gateways.show', ['code' => $code]))
            : JsonResponder::error(__('commerce::gateways.test_failed', ['name' => $gateway->brand(), 'error' => (string) ($result->error?->label() ?? '')]), 422, ['ok' => false]);
    }

    public function primary(string $code): JsonResponse
    {
        $row = $this->gateways->setPrimary(PaymentGatewayCode::tryFrom($code) ?? abort(404));

        return JsonResponder::success(__('commerce::gateways.primary_set', ['name' => $row->code->brand()]), redirect: vendor_route('settings.payment-gateways.show', ['code' => $code]));
    }

    /**
     * @param  list<StoreGatewayRow>  $list
     */
    private function page(array $list, ?StoreGatewayRow $current): View
    {
        return view('commerce::vendor.gateways.index', [
            'list' => $list,
            'current' => $current,
            'form' => $current !== null ? $this->gateways->credentialForm($current->row) : [],
            'modes' => GatewayMode::options(),
            'readiness' => $this->gateways->readiness(),
        ]);
    }
}
