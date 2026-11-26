<?php

namespace App\Modules\Storefront\Http\Requests\Storefront;

use App\Modules\Localization\Models\Currency;
use App\Modules\Localization\Services\StoreCountries;
use App\Modules\Localization\Services\StoreCurrencyService;
use App\Modules\Storefront\DTOs\StorefrontPreferencesData;
use App\Shared\Support\Locale;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST {store}/preferences — the header locale switcher (language / currency / country).
 */
class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'locale' => ['nullable', 'string', Rule::in(array_keys(Locale::enabled()))],
            'currency' => ['nullable', 'string', 'size:3', Rule::exists(Currency::class, 'code')->where('is_active', true), function (string $attribute, mixed $value, Closure $fail): void {
                // VEN-072: only the currencies this store offers in its switcher.
                $offered = app(StoreCurrencyService::class)->switcherCurrencies()->pluck('code')->all();

                if (! in_array(strtoupper((string) $value), $offered, true)) {
                    $fail(__('validation.exists', ['attribute' => $attribute]));
                }
            }],
            'country_id' => ['nullable', 'integer', function (string $attribute, mixed $value, Closure $fail): void {
                if (! app(StoreCountries::class)->isAvailable((int) $value)) {
                    $fail(__('validation.exists', ['attribute' => $attribute]));
                }
            }],
            'redirect' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function toData(): StorefrontPreferencesData
    {
        $country = $this->validated('country_id');

        return new StorefrontPreferencesData(
            locale: $this->validated('locale'),
            currency: $this->validated('currency') !== null ? strtoupper((string) $this->validated('currency')) : null,
            countryId: $country !== null ? (int) $country : null,
        );
    }
}
