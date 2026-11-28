<?php

namespace App\Modules\AfterSales\Http\Requests\StoreApi;

use App\Modules\AfterSales\DTOs\ReturnLineData;
use App\Modules\AfterSales\Models\ReturnReason;
use Illuminate\Validation\Rule;

/**
 * Estimated refund of the return form before it is sent: reason and lines.
 */
class ReturnEstimateRequest extends StoreApiCustomerRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason_code' => ['required', 'string', Rule::exists(ReturnReason::class, 'code')->where('is_active', true)],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.order_item_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function reasonCode(): string
    {
        return (string) $this->validated('reason_code');
    }

    /**
     * @return list<ReturnLineData>
     */
    public function lines(): array
    {
        /** @var list<array{order_item_id: int|string, quantity: int|string}> $items */
        $items = array_values((array) $this->validated('items'));

        return array_map(static fn (array $item): ReturnLineData => new ReturnLineData((int) $item['order_item_id'], (int) $item['quantity']), $items);
    }
}
