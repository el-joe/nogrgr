<?php

namespace App\Shared\Http;

use App\Shared\Routing\VendorPanel;
use App\Shared\Routing\VendorPanelMode;
use App\Shared\Tenancy\CentralDomains;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Which panel an HTML error response belongs to (ADM-120…124, VEN-096…099, QA-FINAL-010):
 * resources/views/errors/{403,404,419,500,503} render resources/views/errors/panel in the
 * panel's own look instead of the central website / storefront page. Detected by path and
 * host, so it also works for URLs no route matched:
 *
 *   central host  /admin/…        → admin   (admin tokens; panel shell when signed in)
 *                 /my-account/…   → owner   (owner tokens; panel shell when signed in)
 *                 /tenant/{store}/… → vendor (vendor kit #101 card)
 *   store host    /admin/…, vendor.* routes → vendor
 *
 * The affiliate panel keeps its own page (Affiliates\Support\AffiliateErrorPage); the
 * storefront and the central website keep theirs; API / AJAX requests get JSON (bootstrap/app.php).
 */
final class PanelErrorPage
{
    public const VIEW = 'errors.panel';

    /** @var list<string> */
    public const CODES = ['403', '404', '419', '500', '503'];

    /**
     * admin | owner | vendor, or null when the request is not a panel page.
     */
    public static function surface(?Request $request = null): ?string
    {
        $request ??= request();

        if (in_array($request->getHost(), CentralDomains::all(), true)) {
            return match (true) {
                $request->is('admin', 'admin/*') => 'admin',
                $request->is('my-account', 'my-account/*') => 'owner',
                $request->is('tenant/*') && ! $request->is('tenant/login', 'tenant/login/*', 'tenant/logout') => 'vendor',
                default => null,
            };
        }

        $name = $request->route()?->getName();

        return $request->is('admin', 'admin/*') || VendorPanel::isVendorRouteName(is_string($name) ? $name : null) ? 'vendor' : null;
    }

    /**
     * View data for errors/panel: links back into the panel and whether the panel shell
     * can be drawn (a signed-in user on a request that ran the session middleware).
     *
     * @return array{surface: string, code: string, message: ?string, dashboardUrl: string, loginUrl: string, backUrl: ?string, retryUrl: string, shell: bool}
     */
    public static function data(string $surface, string $code, ?string $message = null, ?Request $request = null): array
    {
        $request ??= request();
        [$dashboard, $login] = self::urls($surface, $request);
        $previous = SafeRedirect::target($request->headers->get('referer'), $request);

        return [
            'surface' => $surface,
            'code' => in_array($code, self::CODES, true) ? $code : '500',
            'message' => $message,
            'dashboardUrl' => $dashboard,
            'loginUrl' => $login,
            'backUrl' => $previous !== null && $previous !== $request->fullUrl() ? $previous : null,
            'retryUrl' => $request->fullUrl(),
            'shell' => in_array($surface, ['admin', 'owner'], true) && $code !== '503' && self::signedIn($surface, $request),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function urls(string $surface, Request $request): array
    {
        return match ($surface) {
            'admin' => [url('/admin'), self::named('admin.login', url('/admin/login'))],
            'owner' => [url('/my-account'), self::named('central.tenant.login', url('/'))],
            default => self::vendorUrls($request),
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function vendorUrls(Request $request): array
    {
        if (! in_array($request->getHost(), CentralDomains::all(), true)) {
            return [url('/admin'), url('/login')];
        }

        $store = (string) $request->segment(2);

        try {
            $login = VendorPanel::route('login', mode: VendorPanelMode::CentralHost, store: $store !== '' ? $store : null);
        } catch (Throwable) {
            $login = url('/');
        }

        return [$store !== '' ? url('/tenant/'.$store) : url('/'), $login];
    }

    private static function signedIn(string $surface, Request $request): bool
    {
        if (! $request->hasSession() || ! $request->session()->isStarted()) {
            return false;
        }

        try {
            return Auth::guard($surface)->check();
        } catch (Throwable) {
            return false;
        }
    }

    private static function named(string $name, string $fallback): string
    {
        return Route::has($name) ? route($name) : $fallback;
    }
}
