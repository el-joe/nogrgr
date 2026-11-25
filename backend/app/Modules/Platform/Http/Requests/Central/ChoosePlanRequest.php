<?php

namespace App\Modules\Platform\Http\Requests\Central;

use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Platform\Enums\PlanChoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Plan step (WEB-016b): plan, monthly / yearly, free trial or pay now. Whether the plan
 * is active and offers a trial is checked by RegistrationFlowService.
 */
class ChoosePlanRequest extends FormRequest
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
            'plan_id' => ['required', 'integer', 'min:1'],
            'term' => ['required', Rule::enum(BillingTerm::class)],
            'choice' => ['required', Rule::enum(PlanChoice::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'plan_id' => __('platform::signup.plan.plan_attribute'),
            'term' => __('platform::signup.plan.term_attribute'),
            'choice' => __('platform::signup.plan.choice_attribute'),
        ];
    }
}
