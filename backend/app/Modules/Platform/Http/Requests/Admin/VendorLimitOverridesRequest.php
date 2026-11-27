<?php

namespace App\Modules\Platform\Http\Requests\Admin;

use App\Modules\Billing\DTOs\PlanLimitOverridesData;
use App\Modules\Billing\Enums\PlanLimit;
use App\Modules\Billing\Enums\PlanLimitOverrideMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Vendor profile › Limit overrides (ADM-041h): per PlanLimit a mode (plan / custom /
 * unlimited) and, for custom, a whole number 0–100,000,000.
 */
class VendorLimitOverridesRequest extends FormRequest
{
    public const MAX_VALUE = 100_000_000;

    public function authorize(): bool
    {
        return (bool) $this->user('admin')?->can('vendors.limits.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = ['limits' => ['required', 'array:'.implode(',', PlanLimit::values())]];

        foreach (PlanLimit::values() as $limit) {
            $rules["limits.{$limit}"] = ['required', 'array:mode,value'];
            $rules["limits.{$limit}.mode"] = ['required', 'string', Rule::enum(PlanLimitOverrideMode::class)];
            $rules["limits.{$limit}.value"] = ['nullable', "required_if:limits.{$limit}.mode,".PlanLimitOverrideMode::Custom->value, 'integer', 'min:0', 'max:'.self::MAX_VALUE];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (PlanLimit::cases() as $limit) {
            $attributes["limits.{$limit->value}.mode"] = $limit->label();
            $attributes["limits.{$limit->value}.value"] = $limit->label();
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['limits.*.value.required_if' => __('platform::vendors.limits.value_required')];
    }

    public function toData(): PlanLimitOverridesData
    {
        /** @var array<string, array{mode: string, value?: int|string|null}> $limits */
        $limits = $this->validated('limits');

        return PlanLimitOverridesData::fromModes($limits);
    }
}
