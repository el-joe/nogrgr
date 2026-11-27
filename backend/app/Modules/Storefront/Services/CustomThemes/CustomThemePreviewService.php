<?php

namespace App\Modules\Storefront\Services\CustomThemes;

use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\StoreSettingsService;
use App\Modules\Storefront\Enums\CustomThemeVersionStatus;
use App\Modules\Storefront\Models\CustomThemeVersion;
use App\Modules\Storefront\Repositories\Contracts\CustomThemeRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Http\Request;

/**
 * Signed preview of one custom theme version (VEN-036 / ADM-089a, decision #121): the
 * vendor or a reviewing admin gets a short-lived HMAC-signed storefront link
 * (`?ct_preview=<version>&ct_expires=<ts>&ct_signature=…`, bound to store + version +
 * expiry, app key). A valid link keeps the preview in that browser's storefront session
 * for PREVIEW_MINUTES; other shoppers and the live theme are unaffected.
 * `?theme_preview=off` (or `?ct_preview=off`) ends it.
 */
class CustomThemePreviewService extends BaseService
{
    public const SESSION_KEY = 'storefront.custom_theme_preview';

    public const LINK_MINUTES = 10;

    public const PREVIEW_MINUTES = 30;

    public function __construct(private readonly CustomThemeRepositoryContract $themes) {}

    public function url(Tenant $tenant, CustomThemeVersion $version, string $path = '/'): string
    {
        $expires = now()->addMinutes(self::LINK_MINUTES)->getTimestamp();
        $query = ['ct_preview' => $version->id, 'ct_expires' => $expires];
        $query['ct_signature'] = self::signature((string) $tenant->getTenantKey(), $version->id, $expires);

        return StoreSettingsService::storeUrl($tenant).'/'.ltrim($path, '/').'?'.http_build_query($query);
    }

    /**
     * The custom theme version this browser previews, if any.
     */
    public function current(Request $request): ?CustomThemeVersion
    {
        if (! tenancy()->initialized || ! $request->hasSession()) {
            return null;
        }

        $session = $request->session();

        if ($request->query('theme_preview') === 'off' || $request->query('ct_preview') === 'off') {
            $session->forget(self::SESSION_KEY);

            return null;
        }

        $this->start($request);
        $preview = $session->get(self::SESSION_KEY);
        $tenantKey = (string) tenant()?->getTenantKey();

        if (! is_array($preview) || ! is_int($preview['version'] ?? null) || (int) ($preview['until'] ?? 0) < now()->getTimestamp() || ($preview['tenant'] ?? null) !== $tenantKey) {
            if ($preview !== null) {
                $session->forget(self::SESSION_KEY);
            }

            return null;
        }

        $version = $this->themes->findVersion($preview['version']);

        return $version !== null && (string) $version->tenant_id === $tenantKey && $version->status->canPreview() ? $version : null;
    }

    private function start(Request $request): void
    {
        $version = filter_var($request->query('ct_preview'), FILTER_VALIDATE_INT);
        $expires = filter_var($request->query('ct_expires'), FILTER_VALIDATE_INT);
        $signature = $request->query('ct_signature');

        if (! is_int($version) || ! is_int($expires) || ! is_string($signature) || $expires < now()->getTimestamp()) {
            return;
        }

        $tenantKey = (string) tenant()?->getTenantKey();

        if (! hash_equals(self::signature($tenantKey, $version, $expires), $signature)) {
            return;
        }

        $request->session()->put(self::SESSION_KEY, ['tenant' => $tenantKey, 'version' => $version, 'until' => now()->addMinutes(self::PREVIEW_MINUTES)->getTimestamp()]);
    }

    private static function signature(string $tenantKey, int $version, int $expires): string
    {
        return hash_hmac('sha256', implode('|', ['custom-theme-preview', $tenantKey, (string) $version, (string) $expires]), (string) config('app.key'));
    }

    /**
     * Statuses a preview link may be issued for.
     */
    public static function previewable(CustomThemeVersion $version): bool
    {
        return $version->status !== CustomThemeVersionStatus::Failed;
    }
}
