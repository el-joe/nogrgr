<?php

namespace App\Modules\AfterSales\Http\Resources\StoreApi;

use App\Modules\AfterSales\Enums\ReturnBlock;
use App\Modules\AfterSales\Models\ReturnPolicy;
use App\Modules\AfterSales\Models\ReturnReason;
use App\Modules\AfterSales\Services\ReturnPolicyService;
use App\Modules\AfterSales\Support\ReturnUploadLimits;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Support\MoneyPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * "Request return" form data of an order: lines with units left, active reasons, the
 * policy (window, deadline, video, exchanges, upload limits) and whether / why not.
 *
 * @property array{policy: ReturnPolicy, reasons: list<ReturnReason>, deadline: Carbon|null, block: ReturnBlock|null, lines: list<array{item: OrderItem, available: int, returnable: bool}>} $resource
 */
class ReturnOptionsResource extends JsonResource
{
    public function __construct(mixed $resource, private readonly ?Order $order = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $options = $this->resource;
        $policy = $options['policy'];
        $block = $options['block'];
        $money = app(MoneyPresenter::class);

        return [
            'order' => $this->order === null ? null : ['uuid' => $this->order->uuid, 'number' => $this->order->number, 'status' => $this->order->status->value],
            'can_return' => $block === null,
            'blocked_reason' => $block?->value,
            'blocked_message' => $block?->message(),
            'lines' => array_map(static fn (array $line): array => [
                'order_item_id' => $line['item']->id,
                'name' => $line['item']->name,
                'variant_label' => $line['item']->variant_label,
                'sku' => $line['item']->sku,
                'image_url' => $line['item']->image_url,
                'quantity' => $line['item']->quantity,
                'available' => $line['available'],
                'returnable' => $line['returnable'],
                'total' => $money->present($line['item']->grand_total),
            ], $options['lines']),
            'reasons' => array_map(static fn (ReturnReason $reason): array => [
                'code' => $reason->code,
                'name' => $reason->name,
                'requires_video' => $reason->requires_video || $policy->require_video,
            ], $options['reasons']),
            'policy' => [
                'window_days' => $policy->window_days,
                'deadline' => $options['deadline']?->toIso8601String(),
                'require_video' => $policy->require_video,
                'allow_exchanges' => $policy->allow_exchanges,
                'return_shipping_payer' => $policy->return_shipping_payer->value,
                'return_shipping_payer_label' => $policy->return_shipping_payer->label(),
                'uploads' => ReturnUploadLimits::toArray(),
                // The store's own policy text (VEN-079), null when it has none.
                'text' => app(ReturnPolicyService::class)->storeText(),
            ],
        ];
    }
}
