<?php

namespace App\Modules\Localization\Rules;

use App\Modules\Localization\Services\StoreCountries;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The value is the id of a central country that is active and that the current
 * store sells to (StoreCountries).
 */
class StoreCountry implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);

        if ($id === false || ! app(StoreCountries::class)->isAvailable($id)) {
            $fail('validation.exists')->translate();
        }
    }
}
