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
 */
final class CustomThemeCsp
{
    private ?string $nonce = null;

    private bool $used = false;

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

        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-".$this->nonce()."'".$hot,
            "style-src 'self' 'unsafe-inline'".$hot,
            "img-src 'self' data: blob: https:",
            "font-src 'self' data:".$hot,
            "media-src 'self' blob: https:",
            "connect-src 'self'".$hot.$hotWs,
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
}
