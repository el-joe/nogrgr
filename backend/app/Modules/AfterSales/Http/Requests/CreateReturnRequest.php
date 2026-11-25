<?php

namespace App\Modules\AfterSales\Http\Requests;

use App\Modules\AfterSales\DTOs\CreateReturnData;
use App\Modules\AfterSales\Enums\ReturnType;
use App\Modules\AfterSales\Models\ReturnReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create return modal (ADM-021c): one order line, quantity, an active reason and a
 * description of at least 10 characters.
 */
class CreateReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_item_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'reason_code' => ['required', 'string', Rule::exists(ReturnReason::class, 'code')->where('is_active', true)],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'type' => ['nullable', Rule::enum(ReturnType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'order_item_id' => __('after-sales::returns.fields.item'),
            'quantity' => __('after-sales::returns.fields.quantity'),
            'reason_code' => __('after-sales::returns.fields.reason'),
            'description' => __('after-sales::returns.fields.description'),
            'type' => __('after-sales::returns.fields.type'),
        ];
    }

    public function toData(): CreateReturnData
    {
        return new CreateReturnData(
            orderItemId: (int) $this->validated('order_item_id'),
            quantity: (int) $this->validated('quantity'),
            reasonCode: (string) $this->validated('reason_code'),
            description: trim((string) $this->validated('description')),
            type: ReturnType::tryFrom((string) $this->validated('type')) ?? ReturnType::Return,
        );
    }
}
