<?php

namespace App\Modules\Storefront\Http\Requests\Vendor;

use App\Modules\Storefront\DTOs\ThemePageActivationData;
use App\Modules\Storefront\Services\Themes\ThemePageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Activate a theme (VEN-030 card action / VEN-030a "Use … in" modal):
 * {theme: slug, default: 1|0, country_ids: [selling country ids]} — at least one target.
 */
class ThemeActivationRequest extends FormRequest
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
        $pages = app(ThemePageService::class);

        return [
            'theme' => ['required', 'string', 'max:64', Rule::in($pages->catalogue()->pluck('slug')->all())],
            'default' => ['sometimes', 'boolean'],
            'country_ids' => ['sometimes', 'array', 'max:300'],
            'country_ids.*' => ['integer', 'distinct', Rule::in(array_keys($pages->countries()))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'theme.in' => __('content::vendor_themes.errors.theme_unavailable'),
            'country_ids.*.in' => __('content::vendor_themes.errors.country_not_sold'),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->boolean('default') && array_filter((array) $this->input('country_ids', [])) === []) {
                    $validator->errors()->add('country_ids', __('content::vendor_themes.errors.no_target'));
                }
            },
        ];
    }

    public function toData(): ThemePageActivationData
    {
        return new ThemePageActivationData(
            slug: (string) $this->validated('theme'),
            asDefault: $this->boolean('default'),
            countryIds: array_values(array_map('intval', (array) $this->validated('country_ids', []))),
        );
    }
}
