<?php

namespace App\Modules\Billing\Http\Requests\Admin;

use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Billing\Enums\PlanStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin "assign plan manually" (subscriptions page / vendor profile).
 */
class AssignPlanRequest extends FormRequest
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
            'tenant_id' => ['required', 'integer', Rule::exists('tenants', 'id')],
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('status', PlanStatus::Active->value)],
            'term' => ['required', Rule::enum(BillingTerm::class)],
            'mark_paid' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tenant_id' => __('billing::subscriptions.fields.store'),
            'plan_id' => __('billing::subscriptions.fields.plan'),
            'term' => __('billing::subscriptions.fields.term'),
            'mark_paid' => __('billing::subscriptions.fields.mark_paid'),
        ];
    }
}
