<?php

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Modules\Catalog\Enums\AttributeDisplayType;
use App\Modules\Catalog\Enums\RecordStatus;
use App\Modules\Catalog\Support\CatalogLocales;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Attribute create / edit (ADM-015a): translated name, display type and the options
 * repeater options[i] = {id?, name: {locale: text}, swatch (#RRGGBB, colour type), is_active}.
 */
class AttributeRequest extends FormRequest
{
    public const MAX_OPTIONS = 200;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $locales = app(CatalogLocales::class);
        $rules = [
            ...$locales->rules('name', ['string', 'max:80']),
            'display_type' => ['required', Rule::enum(AttributeDisplayType::class)],
            'status' => ['required', Rule::enum(RecordStatus::class)],
            'options' => ['required', 'array', 'min:1', 'max:'.self::MAX_OPTIONS],
            'options.*' => ['required', 'array'],
            'options.*.id' => ['nullable', 'integer', 'min:1', 'distinct'],
            'options.*.name' => ['required', 'array'],
            'options.*.swatch' => ['nullable', 'required_if:display_type,'.AttributeDisplayType::Color->value, 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'options.*.is_active' => ['nullable', 'boolean'],
        ];

        foreach ($locales->codes() as $code) {
            $rules['options.*.name.'.$code] = [$locales->isRequired($code) ? 'required' : 'nullable', 'string', 'max:80'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'name' => __('catalog::attributes.fields.name'),
            'display_type' => __('catalog::attributes.fields.display_type'),
            'status' => __('catalog::attributes.fields.status'),
            'options' => __('catalog::attributes.fields.options'),
            'options.*.swatch' => __('catalog::attributes.fields.swatch'),
        ];

        foreach (app(CatalogLocales::class)->all() as $code => $language) {
            $attributes['options.*.name.'.$code] = __('catalog::attributes.fields.option_name').' ('.$language['name'].')';
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $locales = app(CatalogLocales::class);
        $options = [];

        foreach (array_values((array) $this->validated('options')) as $option) {
            $options[] = [
                'id' => isset($option['id']) && $option['id'] !== '' && $option['id'] !== null ? (int) $option['id'] : null,
                'name' => $locales->clean((array) ($option['name'] ?? [])),
                'swatch' => isset($option['swatch']) && $option['swatch'] !== '' ? strtoupper((string) $option['swatch']) : null,
                'is_active' => filter_var($option['is_active'] ?? true, FILTER_VALIDATE_BOOL),
            ];
        }

        return [
            'name' => $locales->clean($this->validated('name')),
            'display_type' => (string) $this->validated('display_type'),
            'status' => (string) $this->validated('status'),
            'options' => $options,
        ];
    }
}
