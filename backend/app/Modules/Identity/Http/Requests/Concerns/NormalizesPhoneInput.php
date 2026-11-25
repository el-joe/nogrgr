<?php

namespace App\Modules\Identity\Http\Requests\Concerns;

use App\Shared\Support\Phone;
use Closure;

/**
 * intl-tel-input posts the number plus its ISO2 country. Before validation the
 * country is upper-cased and a number that is valid for it is rewritten to E.164,
 * so `unique` checks and storage always see the canonical form; an invalid number
 * is left as typed and fails the `Phone` rule.
 *
 * @phpstan-require-extends \Illuminate\Foundation\Http\FormRequest
 */
trait NormalizesPhoneInput
{
    protected function normalizePhoneInput(string $phoneField = 'phone', string $countryField = 'phone_country'): void
    {
        $country = $this->input($countryField);

        if (is_string($country)) {
            $country = strtoupper(trim($country));
            $this->merge([$countryField => $country]);
        }

        $phone = $this->input($phoneField);

        if (is_string($phone)) {
            $e164 = Phone::e164($phone, is_string($country) ? $country : null);
            $this->merge([$phoneField => $e164 ?? trim($phone)]);
        }
    }

    /**
     * ISO 3166-1 alpha-2 region known to libphonenumber.
     */
    protected function phoneRegionRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if ($value !== null && ! Phone::isRegion(is_string($value) ? $value : null)) {
                $fail('validation.in')->translate();
            }
        };
    }
}
