<?php

use App\Modules\Integrations\Payments\StripeCheckoutDriver;

/*
| Stripe webhook signatures: HMAC-SHA256 of "t.payload", any v1 may match, 5-minute
| tolerance.
*/

$sign = fn (string $payload, int $time, string $secret = 'whsec_1'): string => hash_hmac('sha256', $time.'.'.$payload, $secret);

it('accepts a valid signature inside the tolerance', function () use ($sign): void {
    $driver = new StripeCheckoutDriver;
    $now = 1_800_000_000;

    expect($driver->signatureMatches('{"id":"evt"}', 't='.$now.',v1='.$sign('{"id":"evt"}', $now), 'whsec_1', $now + 10))->toBeTrue()
        ->and($driver->signatureMatches('{"id":"evt"}', 't='.$now.',v1=deadbeef,v1='.$sign('{"id":"evt"}', $now), 'whsec_1', $now))->toBeTrue();
});

it('rejects a wrong secret, a changed payload, a stale timestamp or a malformed header', function () use ($sign): void {
    $driver = new StripeCheckoutDriver;
    $now = 1_800_000_000;
    $header = 't='.$now.',v1='.$sign('{"id":"evt"}', $now);

    expect($driver->signatureMatches('{"id":"evt"}', $header, 'whsec_other', $now))->toBeFalse()
        ->and($driver->signatureMatches('{"id":"evt2"}', $header, 'whsec_1', $now))->toBeFalse()
        ->and($driver->signatureMatches('{"id":"evt"}', $header, 'whsec_1', $now + StripeCheckoutDriver::SIGNATURE_TOLERANCE_SECONDS + 1))->toBeFalse()
        ->and($driver->signatureMatches('{"id":"evt"}', 'v1=abc', 'whsec_1', $now))->toBeFalse()
        ->and($driver->signatureMatches('{"id":"evt"}', '', 'whsec_1', $now))->toBeFalse();
});
