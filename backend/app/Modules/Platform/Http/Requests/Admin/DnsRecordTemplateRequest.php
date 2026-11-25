<?php

namespace App\Modules\Platform\Http\Requests\Admin;

use App\Modules\Platform\DTOs\DnsRecordTemplateData;
use App\Modules\Platform\Enums\DnsRecordType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Platform DNS record template (ADM-045b): type, host ("@", "www", "_nogrgr",
 * placeholders allowed), value, TTL 60–86400, priority (MX only) and sort order.
 */
class DnsRecordTemplateRequest extends FormRequest
{
    /** Host: "@" or DNS labels / underscores / placeholders such as {subdomain}. */
    public const HOST_PATTERN = '/^(@|[A-Za-z0-9_{}*]([A-Za-z0-9_{}.\-]*[A-Za-z0-9_{}])?)$/';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['host', 'value'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }

        if ($this->input('type') !== DnsRecordType::MX->value) {
            $this->merge(['priority' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(DnsRecordType::class)],
            'host' => ['required', 'string', 'max:253', 'regex:'.self::HOST_PATTERN],
            'value' => ['required', 'string', 'max:500'],
            'ttl' => ['required', 'integer', 'min:60', 'max:86400'],
            'priority' => ['nullable', 'required_if:type,'.DnsRecordType::MX->value, 'integer', 'min:0', 'max:65535'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'host.regex' => __('platform::domains.validation.record_host'),
            'priority.required_if' => __('platform::domains.validation.priority_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => __('platform::domains.dns.fields.type'),
            'host' => __('platform::domains.dns.fields.host'),
            'value' => __('platform::domains.dns.fields.value'),
            'ttl' => __('platform::domains.dns.fields.ttl'),
            'priority' => __('platform::domains.dns.fields.priority'),
            'sort_order' => __('platform::domains.dns.fields.sort_order'),
        ];
    }

    public function toData(): DnsRecordTemplateData
    {
        $type = DnsRecordType::from((string) $this->validated('type'));
        $priority = $this->validated('priority');

        return new DnsRecordTemplateData(
            type: $type,
            host: (string) $this->validated('host'),
            value: (string) $this->validated('value'),
            ttl: (int) $this->validated('ttl'),
            priority: $type->requiresPriority() && $priority !== null ? (int) $priority : null,
            sortOrder: (int) $this->validated('sort_order'),
        );
    }
}
