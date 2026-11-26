<?php

use Symfony\Component\Finder\Finder;

/*
| Decision #78 (b): every script is a Vite input served by the app (CLAUDE.md "no CDN").
| The ONE exception (client, 2026-11-26) is Stripe.js `https://js.stripe.com/v3/` for the
| inline Stripe card, and only from the shared loader resources/js/core/payments/stripe-card.js
| (loaded on demand when Stripe is chosen). Anything else fails.
| Scans every Blade view and JS module under resources/ and app/Modules for:
|   - a known third-party script / SDK host (Stripe.js, PayPal SDK, CDNs, tag managers…);
|   - a <script> or <link rel=stylesheet|preload|modulepreload> pointing at another host;
|   - JS that injects a remote script (`.src = 'https://…'`, setAttribute('src', …),
|     dynamic import() / importScripts() of a remote URL).
*/

/** The only file allowed to reference Stripe.js, and the exact URL it may load. */
const STRIPE_LOADER_PATH = 'js/core/payments/stripe-card.js';

const STRIPE_JS_LINE = "const STRIPE_JS = 'https://js.stripe.com/v3/';";

/**
 * @return list<string>
 */
function thirdPartyScriptHosts(): array
{
    return [
        'js.stripe.com', 'm.stripe.network', 'paypal.com/sdk', 'paypalobjects.com',
        'cdn.jsdelivr.net', 'unpkg.com', 'cdnjs.cloudflare.com', 'code.jquery.com', 'ajax.googleapis.com',
        'maps.googleapis.com', 'googletagmanager.com', 'google-analytics.com', 'connect.facebook.net',
        'www.google.com/recaptcha', 'hcaptcha.com', 'cdn.tailwindcss.com', 'static.cloudflareinsights.com',
    ];
}

/**
 * Patterns that load a script (or stylesheet) from another origin.
 *
 * @return array<string, string>
 */
function remoteScriptPatterns(): array
{
    $remote = '(?:https?:)?\/\/';

    return [
        'remote <script src>' => '/<script\b[^>]*\bsrc\s*=\s*["\']?\s*'.$remote.'/i',
        'remote <link> asset' => '/<link\b(?=[^>]*\brel\s*=\s*["\']?(?:stylesheet|preload|modulepreload))[^>]*\bhref\s*=\s*["\']?\s*'.$remote.'/i',
        'script src assignment' => '/\.src\s*=\s*[`"\']\s*'.$remote.'/i',
        'setAttribute src' => '/setAttribute\(\s*[`"\']src[`"\']\s*,\s*[`"\']\s*'.$remote.'/i',
        'remote import()' => '/\bimport\(\s*[`"\']\s*'.$remote.'/i',
        'importScripts' => '/\bimportScripts\s*\(/',
    ];
}

/**
 * @return iterable<SplFileInfo>
 */
function frontendSourceFiles(): iterable
{
    return Finder::create()
        ->files()
        ->in([dirname(__DIR__, 3).'/resources', dirname(__DIR__, 3).'/app/Modules'])
        ->name(['*.blade.php', '*.js', '*.mjs', '*.ts'])
        ->notPath('#(^|/)node_modules/#');
}

/**
 * @return list<string> "path:line — reason"
 */
function thirdPartyScriptViolations(string $path, string $source): array
{
    $violations = [];

    foreach (preg_split('/\R/', $source) ?: [] as $index => $line) {
        if (in_array(str_replace('\\', '/', $path), [STRIPE_LOADER_PATH, 'resources/'.STRIPE_LOADER_PATH], true) && trim($line) === STRIPE_JS_LINE) {
            continue;
        }

        foreach (thirdPartyScriptHosts() as $host) {
            if (stripos($line, $host) !== false) {
                $violations[] = $path.':'.($index + 1).' — third-party host '.$host;
            }
        }

        foreach (remoteScriptPatterns() as $reason => $pattern) {
            if (preg_match($pattern, $line) === 1) {
                $violations[] = $path.':'.($index + 1).' — '.$reason;
            }
        }
    }

    return $violations;
}

it('loads no third-party script on the storefront or any panel', function (): void {
    $violations = [];

    foreach (frontendSourceFiles() as $file) {
        $path = $file->getRelativePathname();
        $violations = [...$violations, ...thirdPartyScriptViolations($path, $file->getContents())];
    }

    expect($violations)->toBe([]);
});

it('allows Stripe.js only through the shared Stripe loader', function (): void {
    $loader = (string) file_get_contents(dirname(__DIR__, 3).'/resources/'.STRIPE_LOADER_PATH);

    expect($loader)->toContain(STRIPE_JS_LINE)
        ->and(thirdPartyScriptViolations('resources/'.STRIPE_LOADER_PATH, $loader))->toBe([])
        // The same line anywhere else, or another Stripe URL in the loader, still fails.
        ->and(thirdPartyScriptViolations('resources/js/storefront/commerce/payment.js', STRIPE_JS_LINE))->not->toBe([])
        ->and(thirdPartyScriptViolations('resources/'.STRIPE_LOADER_PATH, "const SDK = 'https://js.stripe.com/v2/';"))->not->toBe([])
        ->and(thirdPartyScriptViolations('resources/'.STRIPE_LOADER_PATH, "script.src = 'https://cdn.example.com/x.js';"))->not->toBe([]);
});

it('recognises the remote script loaders it guards against', function (string $source): void {
    expect(thirdPartyScriptViolations('fixture', $source))->not->toBe([]);
})->with([
    'Stripe.js constant' => ["const STRIPE_SDK = 'https://js.stripe.com/v3/';"],
    'script tag' => ['<script src="https://cdn.example.com/sdk.js"></script>'],
    'protocol-relative script tag' => ["<script async src='//widgets.example.com/w.js'></script>"],
    'stylesheet' => ['<link rel="stylesheet" href="https://fonts.example.com/a.css">'],
    'injected script' => ["script.src = 'https://sdk.example.com/v1.js';"],
    'setAttribute' => ["el.setAttribute('src', 'https://sdk.example.com/v1.js');"],
    'dynamic import' => ["await import('https://esm.example.com/lib.js');"],
    'PayPal SDK' => ['const url = `https://www.paypal.com/sdk/js?client-id=${id}`;'],
]);

it('allows ordinary links and app-relative assets', function (string $source): void {
    expect(thirdPartyScriptViolations('fixture', $source))->toBe([]);
})->with([
    'anchor to another site' => ['<a href="https://example.com/help" rel="noopener">Help</a>'],
    'relative script' => ['<script src="/build/assets/app.js"></script>'],
    'json island' => ['<script type="application/json" data-config>{"url":"https://example.com"}</script>'],
    'image src from storage' => ['img.src = url;'],
]);
