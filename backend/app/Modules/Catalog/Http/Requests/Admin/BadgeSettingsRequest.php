<?php

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Modules\Catalog\Enums\BadgeAutoFill;
use App\Modules\Catalog\Support\CatalogLocales;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Badge settings card (ADM-017): translated label, ribbon on cards, auto-fill rule and size.
 */
class BadgeSettingsRequest extends FormRequest
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
            ...app(CatalogLocales::class)->rules('name', ['string', 'max:60']),
            'is_active' => ['required', 'boolean'],
            'auto_fill' => ['required', Rule::enum(BadgeAutoFill::class)],
            'auto_fill_limit' => ['required', 'integer', 'min:1', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('catalog::badges.fields.label'),
            'auto_fill' => __('catalog::badges.fields.auto_fill'),
            'auto_fill_limit' => __('catalog::badges.fields.auto_fill_limit'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'name' => app(CatalogLocales::class)->clean($this->validated('name')),
            'is_active' => $this->boolean('is_active'),
            'auto_fill' => (string) $this->validated('auto_fill'),
            'auto_fill_limit' => (int) $this->validated('auto_fill_limit'),
        ];
    }
}
