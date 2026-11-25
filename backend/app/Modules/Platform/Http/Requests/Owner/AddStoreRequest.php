<?php

namespace App\Modules\Platform\Http\Requests\Owner;

use App\Modules\Localization\Models\Country;
use App\Modules\Platform\Http\Requests\Central\StoreAddressCheck;
use App\Modules\Platform\Rules\ValidTenantSlug;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add another store (OWN-007): store name, store address (sub-domain) and country.
 * The plan and payment follow in the standard sign-up steps (WEB-016b / 016c).
 */
class AddStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'store_name' => is_string($this->input('store_name')) ? trim($this->input('store_name')) : $this->input('store_name'),
            'slug' => is_string($this->input('slug')) ? mb_strtolower(trim($this->input('slug'))) : $this->input('slug'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'store_name' => ['required', 'string', 'max:120'],
            'slug' => ['bail', 'required', 'string', 'max:63', new ValidTenantSlug, function (string $attribute, mixed $value, Closure $fail): void {
                $error = app(StoreAddressCheck::class)->slugError((string) $value, null);

                if ($error !== null) {
                    $fail($error);
                }
            }],
            'default_country_id' => ['nullable', 'integer', Rule::exists(Country::class, 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'store_name' => __('platform::signup.add_store.store_name'),
            'slug' => __('platform::signup.add_store.address'),
            'default_country_id' => __('platform::signup.add_store.country'),
        ];
    }
}
