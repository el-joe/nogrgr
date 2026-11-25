<?php

namespace App\Modules\Platform\Http\Requests\Admin;

use App\Modules\Platform\Enums\ImpersonationReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Vendor profile › Impersonate (ADM-041i): why the admin opens the store panel.
 * Details are required for "Other".
 */
class ImpersonateVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('admin')?->can('tenants.impersonate');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(ImpersonationReason::class)],
            'details' => ['nullable', 'required_if:reason,'.ImpersonationReason::Other->value, 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => __('platform::vendors.impersonate.reason'),
            'details' => __('platform::vendors.impersonate.details'),
        ];
    }
}
