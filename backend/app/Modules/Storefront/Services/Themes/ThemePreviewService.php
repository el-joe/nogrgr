<?php

namespace App\Modules\Storefront\Services\Themes;

use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\StoreSettingsService;
use App\Modules\Storefront\DTOs\ResolvedThemeData;
use App\Modules\Storefront\Repositories\Contracts\ThemeRepositoryContract;
use App\Modules\Storefront\Services\StorefrontContext;
use App\Shared\Services\BaseService;
use Illuminate\Http\Request;

/**
 * Vendor theme preview (VEN-030, decision #85): the vendor panel issues a short-lived
 * HMAC-signed storefront link (`?theme_preview=<slug>&tp_country=<id>&tp_expires=<ts>&tp_signature=…`,
 * bound to the store, the slug, the country and the expiry). When the storefront sees a
 * valid link it keeps the preview in that browser's session for PREVIEW_MINUTES, so
 * browsing the store keeps the previewed theme; nobody else's storefront changes and the
 * saved theme (`store_theme`) is untouched. `?theme_preview=off` ends it early.
 */
class ThemePreviewService extends BaseService
{
    public const SESSION_KEY = 'storefront.theme_preview';

    /** Lifetime of the signed link. */
    public const LINK_MINUTES = 10;

    /** How long the storefront keeps the preview once the link was opened. */
    public const PREVIEW_MINUTES = 30;

    public function __construct(private readonly ThemeRepositoryContract $themes) {}

    /**
     * Signed storefront URL previewing a theme (optionally in one shopping country).
     */
    public function url(Tenant $tenant, string $slug, ?int $countryId = null): string
    {
        $expires = now()->addMinutes(self::LINK_MINUTES)->getTimestamp();
        $query = array_filter([
            'theme_preview' => $slug,
            'tp_country' => $countryId,
            'tp_expires' => $expires,
        ], static fn (mixed $value): bool => $value !== null);
        $query['tp_signature'] = self::signature((string) $tenant->getTenantKey(), $slug, $countryId, $expires);

        return StoreSettingsService::storeUrl($tenant).'/?'.http_build_query($query);
    }

    /**
     * The theme the current storefront request previews, if any. A valid signed link
     * starts the preview (and moves the shopping country when the link carries one).
     */
    public function current(Request $request): ?ResolvedThemeData
    {
        if (! tenancy()->initialized || ! $request->hasSession()) {
            return null;
        }

        $session = $request->session();

        if ($request->query('theme_preview') === 'off') {
            $session->forget(self::SESSION_KEY);

            return null;
        }

        $this->start($request);
        $preview = $session->get(self::SESSION_KEY);

        if (! is_array($preview) || ! is_string($preview['slug'] ?? null) || (int) ($preview['until'] ?? 0) < now()->getTimestamp()
            || ($preview['tenant'] ?? null) !== (string) tenant()?->getTenantKey()) {
            if ($preview !== null) {
                $session->forget(self::SESSION_KEY);
            }

            return null;
        }

        $theme = $this->themes->findActiveBySlug($preview['slug']);

        return $theme !== null ? ResolvedThemeData::fromTheme($theme, true) : null;
    }

    private function start(Request $request): void
    {
        $slug = $request->query('theme_preview');
        $expires = filter_var($request->query('tp_expires'), FILTER_VALIDATE_INT);
        $signature = $request->query('tp_signature');
        $country = $request->query('tp_country');
        $countryId = $country === null ? null : filter_var($country, FILTER_VALIDATE_INT);

        if (! is_string($slug) || ! is_int($expires) || ! is_string($signature) || $countryId === false || $expires < now()->getTimestamp()) {
            return;
        }

        $tenantKey = (string) tenant()?->getTenantKey();

        if (! hash_equals(self::signature($tenantKey, $slug, $countryId, $expires), $signature)) {
            return;
        }

        $request->session()->put(self::SESSION_KEY, [
            'tenant' => $tenantKey,
            'slug' => $slug,
            'until' => now()->addMinutes(self::PREVIEW_MINUTES)->getTimestamp(),
        ]);

        if ($countryId !== null) {
            $request->session()->put(StorefrontContext::COUNTRY_SESSION_KEY, $countryId);
        }
    }

    private static function signature(string $tenantKey, string $slug, ?int $countryId, int $expires): string
    {
        return hash_hmac('sha256', implode('|', ['theme-preview', $tenantKey, $slug, (string) $countryId, (string) $expires]), (string) config('app.key'));
    }
}
