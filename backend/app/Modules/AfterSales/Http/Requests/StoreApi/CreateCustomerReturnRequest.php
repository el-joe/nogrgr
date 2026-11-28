<?php

namespace App\Modules\AfterSales\Http\Requests\StoreApi;

use App\Modules\AfterSales\DTOs\CustomerReturnData;
use App\Modules\AfterSales\DTOs\ReturnLineData;
use App\Modules\AfterSales\Enums\ReturnMethod;
use App\Modules\AfterSales\Enums\ReturnType;
use App\Modules\AfterSales\Models\ReturnReason;
use App\Modules\AfterSales\Support\ReturnUploadLimits;
use Illuminate\Validation\Rule;

/**
 * Request return (SF-ECO-030 / APP-016), multipart: reason, description (10–2000),
 * return | exchange, lines [{order_item_id, quantity}], photos and a video.
 */
class CreateCustomerReturnRequest extends StoreApiCustomerRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason_code' => ['required', 'string', Rule::exists(ReturnReason::class, 'code')->where('is_active', true)],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'type' => ['nullable', Rule::enum(ReturnType::class)],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.order_item_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.replacement_variant_id' => ['nullable', 'integer', 'min:1'],
            'return_method' => ['nullable', Rule::enum(ReturnMethod::class)],
            'customer_note' => ['nullable', 'string', 'max:1000'],
        ] + ReturnUploadLimits::rules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason_code' => __('after-sales::returns.fields.reason'),
            'description' => __('after-sales::returns.fields.description'),
            'type' => __('after-sales::returns.fields.type'),
            'items' => __('after-sales::returns.fields.item'),
            'items.*.order_item_id' => __('after-sales::returns.fields.item'),
            'items.*.quantity' => __('after-sales::returns.fields.quantity'),
            'items.*.replacement_variant_id' => __('after-sales::returns.fields.replacement'),
            'return_method' => __('after-sales::returns.fields.return_method'),
            'customer_note' => __('after-sales::returns.fields.customer_note'),
            'photos' => __('after-sales::returns.fields.photos'),
            'photos.*' => __('after-sales::returns.fields.photos'),
            'video' => __('after-sales::returns.fields.video'),
        ];
    }

    public function toData(): CustomerReturnData
    {
        /** @var list<array{order_item_id: int|string, quantity: int|string, replacement_variant_id?: int|string|null}> $items */
        $items = array_values((array) $this->validated('items'));

        return new CustomerReturnData(
            reasonCode: (string) $this->validated('reason_code'),
            description: trim((string) $this->validated('description')),
            type: ReturnType::tryFrom((string) $this->validated('type')) ?? ReturnType::Return,
            lines: array_map(static fn (array $item): ReturnLineData => new ReturnLineData((int) $item['order_item_id'], (int) $item['quantity'], isset($item['replacement_variant_id']) ? (int) $item['replacement_variant_id'] : null), $items),
            photos: $this->uploadedPhotos(),
            video: $this->uploadedVideo(),
            returnMethod: ReturnMethod::tryFrom((string) $this->validated('return_method')),
            customerNote: trim((string) $this->validated('customer_note')) ?: null,
        );
    }
}
