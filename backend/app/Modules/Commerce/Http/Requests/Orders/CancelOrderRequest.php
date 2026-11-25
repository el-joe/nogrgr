<?php

namespace App\Modules\Commerce\Http\Requests\Orders;

use App\Modules\Commerce\DTOs\Orders\CancelOrderData;
use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Enums\CancellationReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Staff cancellation (ADM-021a / VEN-021a): a staff reason code, a note when "other".
 */
class CancelOrderRequest extends FormRequest
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
            'reason' => ['required', 'string', Rule::in(CancellationReason::staffValues())],
            'note' => ['nullable', 'string', 'max:500', 'required_if:reason,'.CancellationReason::Other->value],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['reason' => __('commerce::orders_admin.cancel.reason'), 'note' => __('commerce::orders_admin.cancel.note')];
    }

    public function toData(ActorType $actor, ?int $actorId): CancelOrderData
    {
        $note = $this->validated('note');

        return new CancelOrderData(CancellationReason::from((string) $this->validated('reason')), $actor, $actorId, is_string($note) ? trim($note) : null);
    }
}
