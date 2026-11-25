<?php

namespace App\Modules\Billing\Http\Requests\Admin;

use App\Modules\Billing\Enums\PlanFeature;
use App\Modules\Billing\Enums\PlanLimit;
use App\Modules\Billing\Enums\PlanStatus;
use App\Shared\Support\Icon;
use App\Shared\Support\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Plan create / edit (ADM-046a). Name is required in every enabled locale; prices are
 * USD decimals (stored as minor units by PlanData); empty limit = unlimited.
 */
class PlanRequest extends FormRequest
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
        $rules = [
            'name' => ['required', 'array'],
            'description' => ['nullable', 'array'],
            'highlights' => ['nullable', 'array'],
            'icon' => ['nullable', 'string', Rule::in(Icon::names())],
            'status' => ['required', Rule::enum(PlanStatus::class)],
            'monthly_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:1000000'],
            'yearly_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:10000000'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'limits' => ['nullable', 'array'],
            'features' => ['nullable', 'array'],
        ];

        foreach (array_keys(Locale::enabled()) as $locale) {
            $rules['name.'.$locale] = ['required', 'string', 'max:100'];
            $rules['description.'.$locale] = ['nullable', 'string', 'max:500'];
            $rules['highlights.'.$locale] = ['nullable', 'string', 'max:2000'];
        }

        foreach (PlanLimit::values() as $limit) {
            $rules['limits.'.$limit] = ['nullable', 'integer', 'min:0', 'max:100000000'];
        }

        foreach (PlanFeature::values() as $feature) {
            $rules['features.'.$feature] = ['nullable', 'boolean'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'name' => __('billing::plans.fields.name'),
            'description' => __('billing::plans.fields.description'),
            'highlights' => __('billing::plans.fields.highlights'),
            'icon' => __('billing::plans.fields.icon'),
            'status' => __('billing::plans.fields.status'),
            'monthly_price' => __('billing::plans.fields.monthly_price'),
            'yearly_price' => __('billing::plans.fields.yearly_price'),
            'trial_days' => __('billing::plans.fields.trial_days'),
            'sort_order' => __('billing::plans.fields.sort_order'),
        ];

        foreach (PlanLimit::cases() as $limit) {
            $attributes['limits.'.$limit->value] = $limit->label();
        }

        return $attributes;
    }
}
