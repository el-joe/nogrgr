<?php

namespace App\Modules\Storefront\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Page builder draft (VEN-032): the sections in display order with their settings. Shape
 * only — StoreLayoutService::normalise() keeps what each section's schema declares.
 */
class PageBuilderDraftRequest extends FormRequest
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
            'sections' => ['nullable', 'array', 'max:60'],
            'sections.*' => ['array'],
            'sections.*.key' => ['nullable', 'string', 'max:64'],
            'sections.*.type' => ['nullable', 'string', 'max:32'],
            'sections.*.enabled' => ['nullable', 'boolean'],
            'sections.*.settings' => ['nullable', 'array'],
            'sections.*.settings.limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sections.*.settings.url' => ['nullable', 'string', 'max:500'],
            'sections.*.countries' => ['nullable', 'array', 'max:300'],
            'sections.*.countries.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sections' => (string) __('content::page_builder.sections_title'),
            'sections.*.settings.limit' => (string) __('content::page_builder.fields.limit'),
            'sections.*.settings.url' => (string) __('content::page_builder.fields.url'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sections(): array
    {
        $sections = $this->input('sections', []);

        return is_array($sections) ? array_values(array_filter($sections, 'is_array')) : [];
    }
}
