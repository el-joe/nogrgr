<?php

namespace App\Shared\Support;

use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

/**
 * Phone helpers on top of libphonenumber (propaganistas/laravel-phone). Validation
 * itself is the package's `Phone` rule; this normalises validated input for storage.
 */
final class Phone
{
    /**
     * E.164 ("+966501234567") of a number valid for $country, or null.
     */
    public static function e164(?string $number, ?string $country): ?string
    {
        if ($number === null || trim($number) === '') {
            return null;
        }

        try {
            $phone = new PhoneNumber($number, $country !== null ? [strtoupper($country)] : []);

            return $phone->isValid() ? $phone->formatE164() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Whether $country is an ISO 3166-1 alpha-2 region known to libphonenumber.
     */
    public static function isRegion(?string $country): bool
    {
        return is_string($country) && PhoneNumber::isValidCountry(strtoupper($country));
    }
}
