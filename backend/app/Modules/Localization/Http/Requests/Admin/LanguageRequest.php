<?php

namespace App\Modules\Localization\Http\Requests\Admin;

use App\Modules\Localization\Enums\TextDirection;
use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Models\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create (code required, unique) and edit (code fixed) of a central language.
 * One USD price per language (decision #45), required except for ar / en (always free).
 */
class LanguageRequest extends FormRequest
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
        $language = $this->route('language');
        $creating = ! $language instanceof Language;
        $core = ! $creating && $language->isCore();

        return [
            'code' => $creating
                ? ['required', 'string', 'max:12', 'regex:/^[a-z]{2,3}(-[a-zA-Z0-9]{2,8})?$/', Rule::unique(Language::class, 'code')]
                : ['prohibited'],
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:100'],
            'name.ar' => ['required', 'string', 'max:100'],
            'native_name' => ['required', 'string', 'max:100'],
            'direction' => ['required', Rule::enum(TextDirection::class)],
            'flag' => ['nullable', 'string', 'size:2', 'alpha'],
            'is_active' => ['required', 'boolean'],
            'price' => $core
                ? ['prohibited']
                : ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999'],
            'country_ids' => ['nullable', 'array'],
            'country_ids.*' => ['integer', Rule::exists(Country::class, 'id')],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => __('localization::admin.languages.fields.code'),
            'name.en' => __('localization::admin.fields.name_en'),
            'name.ar' => __('localization::admin.fields.name_ar'),
            'native_name' => __('localization::admin.languages.fields.native_name'),
            'direction' => __('localization::admin.languages.fields.direction'),
            'flag' => __('localization::admin.languages.fields.flag'),
            'price' => __('localization::admin.languages.fields.price'),
            'country_ids' => __('localization::admin.languages.fields.countries'),
            'sort_order' => __('localization::admin.fields.sort_order'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $language = $this->route('language');

        return [
            ...$this->validated(),
            'code' => $language instanceof Language ? $language->code : (string) $this->validated('code'),
            'is_active' => $this->boolean('is_active'),
            'sort_order' => (int) $this->validated('sort_order'),
            'price' => $this->filled('price') ? (string) $this->validated('price') : null,
        ];
    }
}
