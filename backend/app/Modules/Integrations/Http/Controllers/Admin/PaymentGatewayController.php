<?php

namespace App\Modules\Integrations\Http\Controllers\Admin;

use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Http\Requests\Admin\ToggleGatewayRequest;
use App\Modules\Integrations\Http\Requests\Admin\UpdatePaymentGatewayRequest;
use App\Modules\Integrations\Services\PaymentGatewayService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * Admin › Integrations › Payment gateways (ADM-100a list + toggle + mode badges,
 * ADM-100b edit: vendor-payments and tenant-registration configs, fees, test).
 */
class PaymentGatewayController extends Controller
{
    public function __construct(private readonly PaymentGatewayService $gateways) {}

    public function index(): View
    {
        return view('integrations::admin.gateways.index', [
            'gateways' => $this->gateways->all(),
            'current' => null,
        ]);
    }

    public function edit(PaymentGatewayCode $gateway): View
    {
        $model = $this->gateways->find($gateway);
        $configs = [];

        foreach (GatewayPurpose::cases() as $purpose) {
            $configs[$purpose->value] = [
                'config' => $this->gateways->config($model, $purpose),
                'credentials' => $this->gateways->credentialForm($model, $purpose),
            ];
        }

        return view('integrations::admin.gateways.edit', [
            'gateways' => $this->gateways->all(),
            'current' => $model,
            'configs' => $configs,
            'currencyOptions' => $this->gateways->currencyOptions(),
        ]);
    }

    public function update(UpdatePaymentGatewayRequest $request, PaymentGatewayCode $gateway): JsonResponse
    {
        $this->gateways->update($this->gateways->find($gateway), $request->payload());

        return JsonResponder::success(__('integrations::admin.gateways.saved', ['name' => $gateway->brand()]), redirect: route('admin.integrations.gateways.edit', $gateway));
    }

    public function toggle(ToggleGatewayRequest $request, PaymentGatewayCode $gateway): JsonResponse
    {
        try {
            $this->gateways->setActive($this->gateways->find($gateway), $request->active());
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::success(__($request->active() ? 'integrations::admin.gateways.activated' : 'integrations::admin.gateways.deactivated', ['name' => $gateway->brand()]));
    }

    public function test(PaymentGatewayCode $gateway, GatewayPurpose $purpose): JsonResponse
    {
        $result = $this->gateways->test($this->gateways->find($gateway), $purpose);
        $data = ['status' => $result->status()->value, 'status_label' => $result->status()->label(), 'error' => $result->error?->value, 'detail' => $result->detail];

        return $result->ok
            ? JsonResponder::success($result->message(), $data)
            : JsonResponder::error($result->message(), 422, $data);
    }
}
