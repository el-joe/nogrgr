<?php

use Symfony\Component\Finder\Finder;

/*
| Decision #69: .env holds infrastructure only. Integrations (gateways, mail, social login,
| Reverb / Pusher client, Cloudflare, AI, geocoding, …) and business settings (billing,
| checkout limits, DNS defaults) come from DB settings. Any env() call in config/ or app/
| must read an allow-listed infrastructure key with a literal name.
*/

/**
 * Exact infrastructure keys.
 *
 * @return list<string>
 */
function envAllowedKeys(): array
{
    return [
        'CENTRAL_DOMAINS', 'TENANT_BASE_DOMAIN', 'FILESYSTEM_DISK', 'BCRYPT_ROUNDS',
        'SANCTUM_STATEFUL_DOMAINS', 'SANCTUM_TOKEN_PREFIX', 'DATATABLES_ERROR', 'MYSQL_ATTR_SSL_CA',
        // Non-delivering sinks only (config/mail.php, config/broadcasting.php clamp the value).
        'MAIL_MAILER', 'BROADCAST_CONNECTION',
        // Reverb server process (bind, scaling, per-process tunables); app keys come from the DB.
        'REVERB_MAX_REQUEST_SIZE', 'REVERB_APP_PING_INTERVAL', 'REVERB_APP_ACTIVITY_TIMEOUT',
        'REVERB_APP_MAX_CONNECTIONS', 'REVERB_APP_MAX_MESSAGE_SIZE', 'REVERB_APP_MAX_CHANNELS_PER_CONNECTION',
        'REVERB_APP_ACCEPT_CLIENT_EVENTS_FROM', 'REVERB_APP_RATE_LIMITING_ENABLED',
        'REVERB_APP_RATE_LIMIT_MAX_ATTEMPTS', 'REVERB_APP_RATE_LIMIT_DECAY_SECONDS', 'REVERB_APP_RATE_LIMIT_TERMINATE',
    ];
}

/**
 * Infrastructure key prefixes (framework connections, logging, process settings).
 *
 * @return list<string>
 */
function envAllowedPrefixes(): array
{
    return [
        'APP_', 'DB_', 'CACHE_', 'QUEUE_', 'SESSION_', 'REDIS_', 'LOG_', 'PAPERTRAIL_', 'AUTH_',
        'MEMCACHED_', 'BEANSTALKD_', 'HORIZON_', 'TENANCY_', 'REVERB_SERVER', 'REVERB_SCALING_',
        'REVERB_PULSE_', 'REVERB_TELESCOPE_',
    ];
}

function envKeyAllowed(string $key): bool
{
    if (in_array($key, envAllowedKeys(), true)) {
        return true;
    }

    foreach (envAllowedPrefixes() as $prefix) {
        if (str_starts_with($key, $prefix)) {
            return true;
        }
    }

    return false;
}

it('reads only infrastructure keys from env in config and app code', function (): void {
    $root = dirname(__DIR__, 3);
    $finder = (new Finder)->files()->in([$root.'/config', $root.'/app'])->name('*.php');
    $violations = [];

    foreach ($finder as $file) {
        $tokens = PhpToken::tokenize($file->getContents());

        foreach ($tokens as $index => $token) {
            if (! $token->is(T_STRING) || strtolower($token->text) !== 'env') {
                continue;
            }

            $previous = $tokens[$index - 1] ?? null;
            $previous = $previous !== null && $previous->is(T_WHITESPACE) ? ($tokens[$index - 2] ?? null) : $previous;

            // Method calls / declarations (->env(), function env()) are not the helper.
            if ($previous !== null && ($previous->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION]))) {
                continue;
            }

            $next = $index + 1;
            while (isset($tokens[$next]) && $tokens[$next]->is(T_WHITESPACE)) {
                $next++;
            }

            if (($tokens[$next]->text ?? null) !== '(') {
                continue;
            }

            $argument = $next + 1;
            while (isset($tokens[$argument]) && $tokens[$argument]->is(T_WHITESPACE)) {
                $argument++;
            }

            $location = $file->getRelativePathname().':'.$token->line;
            $literal = $tokens[$argument] ?? null;

            if ($literal === null || ! $literal->is(T_CONSTANT_ENCAPSED_STRING)) {
                $violations[] = $location.' (non-literal key)';

                continue;
            }

            $key = trim($literal->text, '\'"');

            if (! envKeyAllowed($key)) {
                $violations[] = $location.' '.$key;
            }
        }
    }

    expect($violations)->toBe([], "Move these to DB settings (Admin › Integrations / Settings), decision #69:\n".implode("\n", $violations));
});

it('allows infrastructure keys and rejects integration keys', function (string $key, bool $allowed): void {
    expect(envKeyAllowed($key))->toBe($allowed);
})->with([
    ['DB_HOST', true],
    ['REVERB_SERVER_PORT', true],
    ['MAIL_MAILER', true],
    ['MAIL_PASSWORD', false],
    ['OPENAI_API_KEY', false],
    ['REVERB_APP_KEY', false],
    ['STRIPE_SECRET', false],
    ['TENANT_EDGE_IPV4', false],
    ['BILLING_TAX_RATE_BP', false],
]);
