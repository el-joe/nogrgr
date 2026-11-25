<?php

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Modules\Catalog\Enums\LabelPlaceholder;
use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Localization\Models\Country;
use App\Shared\Support\Icon;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Label create / edit (ADM-016a): icon, translated text whose {placeholders} must be
 * known (LabelPlaceholder), countries (empty = all), sort and active.
 */
class ProductLabelRequest extends FormRequest
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
        $placeholders = static function (string $attribute, mixed $value, Closure $fail): void {
            preg_match_all('/\{([^{}]*)\}/', (string) $value, $matches);
            $unknown = array_diff($matches[0], LabelPlaceholder::tokens());

            if ($unknown !== []) {
                $fail(__('catalog::labels.unknown_placeholder', ['placeholder' => reset($unknown), 'allowed' => implode(', ', LabelPlaceholder::tokens())]));
            }
        };

        return [
            ...app(CatalogLocales::class)->rules('text', ['string', 'max:120', $placeholders]),
            'icon' => ['nullable', 'string', Rule::in(Icon::names())],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'country_ids' => ['nullable', 'array'],
            'country_ids.*' => ['integer', Rule::exists(Country::class, 'id')],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'text' => __('catalog::labels.fields.text'),
            'icon' => __('catalog::labels.fields.icon'),
            'sort_order' => __('catalog::labels.fields.sort_order'),
            'country_ids' => __('catalog::labels.fields.countries'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'text' => app(CatalogLocales::class)->clean($this->validated('text')),
            'icon' => $this->filled('icon') ? (string) $this->validated('icon') : null,
            'sort_order' => $this->filled('sort_order') ? (int) $this->validated('sort_order') : null,
            'country_ids' => $this->filled('country_ids') ? array_map('intval', (array) $this->validated('country_ids')) : null,
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
