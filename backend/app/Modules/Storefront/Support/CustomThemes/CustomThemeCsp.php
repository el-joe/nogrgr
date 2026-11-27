<?php

namespace App\Modules\Storefront\Support\CustomThemes;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;

/**
 * Content-Security-Policy of custom theme pages (decision #121), one nonce per request.
 * Scripts: our own origin (validated package JS, our runtime bundle) and nonce'd tags
 * only — no inline scripts, no event handlers, no eval, no remote hosts; styles: own
 * origin + inline styles; images / media also from https (product images on a CDN);
 * no plugins, no frames, forms only to the store. Sent as a header by
 * ApplyCustomThemeSecurityHeaders and as a <meta> by head() (error pages).
 * Store tracking pixels (decision #91): only after the shopper accepted the consent
 * notice (allowTracking), the hosts of the store's enabled pixels are added — the SDK host
 * to script-src and the pixel's collection endpoints to connect-src. Nothing else.
 */
final class CustomThemeCsp
{
    private ?string $nonce = null;

    private bool $used = false;

    /**
     * Hosts per tracking pixel (TrackingPixelService keys): script = the one SDK host
     * tracking.js loads from (#91), connect = where the SDK sends its events.
     *
     * @var array<string, array{script: list<string>, connect: list<string>}>
     */
    public const PIXEL_HOSTS = [
        'meta' => ['script' => ['https://connect.facebook.net'], 'connect' => ['https://connect.facebook.net', 'https://www.facebook.com']],
        'tiktok' => ['script' => ['https://analytics.tiktok.com'], 'connect' => ['https://analytics.tiktok.com']],
        'snap' => ['script' => ['https://sc-static.net'], 'connect' => ['https://tr.snapchat.com', 'https://tr-shadow.snapchat.com']],
        'ga4' => ['script' => ['https://www.googletagmanager.com'], 'connect' => ['https://www.googletagmanager.com', 'https://*.google-analytics.com', 'https://*.analytics.google.com']],
    ];

    /** @var list<string> */
    private array $pixels = [];

    /**
     * Allow the hosts of these pixels (consent given). Unknown keys are ignored.
     *
     * @param  list<string>  $pixels
     */
    public function allowTracking(array $pixels): void
    {
        $this->pixels = array_values(array_intersect(array_keys(self::PIXEL_HOSTS), $pixels));
    }

    /**
     * @return list<string>
     */
    public function trackingPixels(): array
    {
        return $this->pixels;
    }

    public function nonce(): string
    {
        return $this->nonce ??= Str::random(32);
    }

    public function markUsed(): void
    {
        $this->used = true;
    }

    public function used(): bool
    {
        return $this->used;
    }

    public function policy(bool $forMeta = false): string
    {
        $hot = Vite::isRunningHot() ? ' '.rtrim((string) @file_get_contents(Vite::hotFile())) : '';
        $hotWs = $hot !== '' ? ' '.str_replace(['http://', 'https://'], ['ws://', 'wss://'], trim($hot)) : '';

        $scriptHosts = $this->hosts('script');
        $connectHosts = $this->hosts('connect');

        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-".$this->nonce()."'".$scriptHosts.$hot,
            "style-src 'self' 'unsafe-inline'".$hot,
            "img-src 'self' data: blob: https:",
            "font-src 'self' data:".$hot,
            "media-src 'self' blob: https:",
            "connect-src 'self'".$connectHosts.$hot.$hotWs,
            "object-src 'none'",
            "frame-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        if (! $forMeta) {
            $directives[] = "frame-ancestors 'self'";
        }

        return implode('; ', $directives);
    }

    /**
     * Space-prefixed host list of the allowed pixels for one directive ('' when none).
     */
    private function hosts(string $directive): string
    {
        $hosts = [];

        foreach ($this->pixels as $pixel) {
            array_push($hosts, ...self::PIXEL_HOSTS[$pixel][$directive]);
        }

        $hosts = array_values(array_unique($hosts));

        return $hosts === [] ? '' : ' '.implode(' ', $hosts);
    }
}
