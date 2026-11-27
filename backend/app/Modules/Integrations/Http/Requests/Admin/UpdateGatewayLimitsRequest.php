<?php

namespace App\Modules\Integrations\Http\Requests\Admin;

use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Shared\Money\Money;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT limits[<gateway>][limit] (USD, empty = no limit) and limits[<gateway>][warn_percent]
 * (1–99) of Admin › Integrations › Gateway limits (ADM-100c).
 */
class UpdateGatewayLimitsRequest extends FormRequest
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
            'limits' => ['required', 'array:'.implode(',', PaymentGatewayCode::values())],
            'limits.*.limit' => ['nullable', 'numeric', 'min:0', 'max:100000000', 'decimal:0,2'],
            'limits.*.warn_percent' => ['required', 'integer', 'between:1,99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (PaymentGatewayCode::cases() as $code) {
            $attributes['limits.'.$code->value.'.limit'] = __('integrations::admin.limits.fields.limit').' ('.$code->brand().')';
            $attributes['limits.'.$code->value.'.warn_percent'] = __('integrations::admin.limits.fields.warn_percent').' ('.$code->brand().')';
        }

        return $attributes;
    }

    /**
     * @return array<string, array{limit_minor: int|null, warn_percent: int}>
     */
    public function limits(): array
    {
        $rows = [];

        foreach ((array) $this->validated('limits') as $code => $row) {
            $limit = $row['limit'] ?? null;
            $rows[(string) $code] = [
                'limit_minor' => $limit === null || $limit === '' ? null : Money::of((string) $limit)->minor,
                'warn_percent' => (int) $row['warn_percent'],
            ];
        }

        return $rows;
    }
}
