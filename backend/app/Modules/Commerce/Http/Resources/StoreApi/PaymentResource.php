<?php

namespace App\Modules\Commerce\Http\Resources\StoreApi;

use App\Modules\Commerce\Models\OrderPayment;
use App\Modules\Commerce\Support\MoneyPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One payment attempt (no secrets; the client secret is only returned by "start").
 *
 * @property OrderPayment $resource
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payment = $this->resource;

        return [
            'reference' => $payment->reference,
            'gateway' => $payment->gateway->value,
            'source' => $payment->source->value,
            'mode' => $payment->mode->value,
            'status' => $payment->status->value,
            'status_label' => $payment->status->label(),
            'amount' => app(MoneyPresenter::class)->present($payment->amount),
            'failure_reason' => $payment->failure_reason,
        ];
    }
}
