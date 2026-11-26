<?php

namespace App\Modules\Integrations\Exceptions;

use RuntimeException;

/**
 * A hosted-checkout call failed (misconfigured gateway, gateway error, unreachable)
 * or a webhook did not pass its signature check. `detail` is the gateway's own
 * message, never a secret; the message shown to visitors is translated.
 */
final class PaymentGatewayException extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly ?string $detail = null)
    {
        parent::__construct($detail ?? $reason);
    }

    public static function misconfigured(): self
    {
        return new self('misconfigured');
    }

    public static function gateway(?string $detail): self
    {
        return new self('gateway_error', $detail);
    }

    public static function unreachable(?string $detail = null): self
    {
        return new self('unreachable', $detail);
    }

    public static function invalidSignature(): self
    {
        return new self('invalid_signature');
    }

    /**
     * A charge / refund in another currency than USD reached a driver (client rule
     * 2026-11-26: USD is the only payment currency; others are display-only).
     */
    public static function nonBaseCurrency(string $currency): self
    {
        return new self('non_usd_currency', 'Payments are charged in USD only, got '.$currency.'.');
    }

    public static function unsupported(): self
    {
        return new self('unsupported');
    }
}
