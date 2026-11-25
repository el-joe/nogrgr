<?php

namespace App\Modules\Platform\Http\Requests\Admin;

use App\Modules\Platform\DTOs\ComplianceDecisionData;
use App\Modules\Platform\Enums\ComplianceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Compliance review decision (ADM-044a): verified, needs_action or rejected. A note to
 * the vendor is required unless the store is verified.
 */
class ComplianceDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('note'))) {
            $note = trim($this->input('note'));
            $this->merge(['note' => $note === '' ? null : $note]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([ComplianceStatus::Verified->value, ComplianceStatus::NeedsAction->value, ComplianceStatus::Rejected->value])],
            'note' => ['nullable', 'string', 'max:2000', 'required_unless:status,'.ComplianceStatus::Verified->value],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required_unless' => __('platform::messages.note_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => __('platform::compliance.columns.status'),
            'note' => __('platform::compliance.decision.note'),
        ];
    }

    public function toData(): ComplianceDecisionData
    {
        $adminId = $this->user('admin')?->getAuthIdentifier();

        return new ComplianceDecisionData(
            status: ComplianceStatus::from((string) $this->validated('status')),
            note: $this->validated('note'),
            adminId: $adminId !== null ? (int) $adminId : null,
        );
    }
}
