<?php

namespace App\Modules\Storefront\Services\Layouts;

use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\StoreSettingsService;
use App\Modules\Storefront\Models\StoreLayout;
use App\Modules\Storefront\Repositories\Contracts\StoreLayoutRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Http\Request;

/**
 * Signed draft preview of a page-builder layout (VEN-032, same pattern as the VEN-030 theme
 * preview, decision #85): `?lp=<layout id>&lp_expires=<ts>&lp_signature=…` (HMAC, app key,
 * bound to store + layout + expiry). Stateless — every request of the builder's preview
 * iframe carries the link, so it also works where the iframe gets no session cookie; other
 * shoppers and the published layout are unaffected.
 */
class StoreLayoutPreviewService extends BaseService
{
    public const LINK_MINUTES = 30;

    public function __construct(private readonly StoreLayoutRepositoryContract $layouts) {}

    public function url(Tenant $tenant, StoreLayout $layout, string $path = '/'): string
    {
        $expires = now()->addMinutes(self::LINK_MINUTES)->getTimestamp();
        $query = ['lp' => $layout->id, 'lp_expires' => $expires];
        $query['lp_signature'] = self::signature((string) $tenant->getTenantKey(), $layout->id, $expires);

        return StoreSettingsService::storeUrl($tenant).'/'.ltrim($path, '/').'?'.http_build_query($query);
    }

    /**
     * The layout previewed by this request (valid, unexpired link of this store), if any.
     */
    public function current(Request $request): ?StoreLayout
    {
        if (! tenancy()->initialized) {
            return null;
        }

        $id = filter_var($request->query('lp'), FILTER_VALIDATE_INT);
        $expires = filter_var($request->query('lp_expires'), FILTER_VALIDATE_INT);
        $signature = $request->query('lp_signature');

        if (! is_int($id) || ! is_int($expires) || ! is_string($signature) || $expires < now()->getTimestamp()
            || ! hash_equals(self::signature((string) tenant()?->getTenantKey(), $id, $expires), $signature)) {
            return null;
        }

        $layout = $this->layouts->find($id);

        return $layout instanceof StoreLayout ? $layout : null;
    }

    private static function signature(string $tenantKey, int $layoutId, int $expires): string
    {
        return hash_hmac('sha256', implode('|', ['layout-preview', $tenantKey, (string) $layoutId, (string) $expires]), (string) config('app.key'));
    }
}
