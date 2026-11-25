<?php

namespace App\Modules\Platform\Http\Requests\Admin;

use App\Modules\Platform\Enums\ComplianceDocumentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Flag one compliance document as verified or needing action.
 */
class ComplianceDocumentStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in([ComplianceDocumentStatus::Verified->value, ComplianceDocumentStatus::NeedsAction->value])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['status' => __('platform::compliance.columns.status')];
    }

    public function documentStatus(): ComplianceDocumentStatus
    {
        return ComplianceDocumentStatus::from((string) $this->validated('status'));
    }
}
