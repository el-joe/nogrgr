<?php

namespace App\Modules\Platform\Http\Requests\Central;

use App\Modules\Platform\Services\VendorRegistrationService;
use App\Modules\Platform\Support\TenantSlug;
use Illuminate\Support\Str;

/**
 * Store-address rules shared by the store step (CompleteRegistrationStoreRequest) and
 * the live availability check (GET register/availability): normalisation + the
 * human message for an invalid, reserved, taken or platform-owned address.
 */
final class StoreAddressCheck
{
    public function __construct(private readonly VendorRegistrationService $registrations) {}

    public static function normalizeSlug(mixed $value): mixed
    {
        return is_string($value) ? Str::lower(trim($value)) : $value;
    }

    /**
     * "https://Shop.Example.com/path" → "shop.example.com".
     */
    public static function normalizeDomain(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $host = Str::lower(trim($value));
        $host = (string) preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $host);
        $host = (string) Str::before(Str::before($host, '/'), '?');

        return rtrim($host, '.');
    }

    /**
     * Null when the address can be used, otherwise the message to show.
     */
    public function slugError(string $slug, ?int $registrationId): ?string
    {
        if (! TenantSlug::isValid($slug)) {
            return __('platform::validation.tenant_slug');
        }

        if ($this->registrations->slugAvailable($slug, $registrationId)) {
            return null;
        }

        $host = TenantSlug::subdomainHost($slug);
        $suggestions = $this->registrations->slugSuggestions($slug, $registrationId);

        return $suggestions === []
            ? __('platform::register.store.taken', ['host' => $host])
            : __('platform::register.store.taken_try', ['host' => $host, 'suggestions' => implode(' '.__('platform::register.store.or').' ', $suggestions)]);
    }

    public function domainError(string $host): ?string
    {
        $base = Str::lower((string) config('tenancy.base_domain'));

        return match (true) {
            ! self::isHostname($host) => __('platform::register.store.domain_invalid'),
            $base !== '' && ($host === $base || str_ends_with($host, '.'.$base)) => __('platform::register.store.domain_platform', ['base' => $base]),
            ! $this->registrations->domainAvailable($host) => __('platform::register.store.domain_taken', ['host' => $host]),
            default => null,
        };
    }

    public static function isHostname(string $host): bool
    {
        return strlen($host) <= 253
            && preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host) === 1;
    }
}
