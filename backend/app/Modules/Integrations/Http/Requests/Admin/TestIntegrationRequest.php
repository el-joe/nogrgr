<?php

namespace App\Modules\Integrations\Http\Requests\Admin;

use App\Modules\Integrations\Enums\IntegrationSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Test connection" / "Send test email": mail needs a recipient, AI a provider.
 */
class TestIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function section(): IntegrationSection
    {
        /** @var IntegrationSection $section */
        $section = $this->route('section');

        return $section;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return match ($this->section()) {
            IntegrationSection::Mail => ['email' => ['required', 'string', 'email:rfc', 'max:191']],
            IntegrationSection::Ai => ['target' => ['required', 'string', Rule::in($this->section()->testTargets())]],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => __('integrations::admin.mail.test_recipient'),
            'target' => __('integrations::admin.sections.ai.provider_label'),
        ];
    }

    public function target(): ?string
    {
        return match ($this->section()) {
            IntegrationSection::Mail => (string) $this->validated('email'),
            IntegrationSection::Ai => (string) $this->validated('target'),
            default => null,
        };
    }
}
