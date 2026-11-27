<?php

namespace App\Modules\Storefront\Http\Controllers\Storefront;

use App\Modules\Storefront\Services\CustomThemes\CustomThemePackageStore;
use App\Modules\Storefront\Services\CustomThemes\CustomTwigThemeRenderer;
use App\Shared\Http\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams a validated asset of one of the current store's custom theme versions
 * (`/theme-assets/{version}/{path}`, decision #121): only paths under assets/ from the
 * version's validated file list, fixed MIME type per extension, nosniff, immutable cache
 * (a version never changes); SVGs get a script-free CSP. Other stores' versions 404.
 */
class ThemeAssetController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const TYPES = [
        'css' => 'text/css; charset=UTF-8', 'js' => 'text/javascript; charset=UTF-8',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif',
        'avif' => 'image/avif', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon',
        'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'otf' => 'font/otf',
    ];

    public function __invoke(CustomTwigThemeRenderer $renderer, CustomThemePackageStore $store, int $version, string $path): BinaryFileResponse
    {
        $model = $renderer->version($version);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $file = $model !== null && isset(self::TYPES[$extension]) ? $store->path($model, 'assets/'.$path) : null;

        abort_if($file === null, 404);

        $response = new BinaryFileResponse($file, 200, [
            'Content-Type' => self::TYPES[$extension],
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);

        if ($extension === 'svg') {
            $response->headers->set('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
        }

        return $response;
    }
}
