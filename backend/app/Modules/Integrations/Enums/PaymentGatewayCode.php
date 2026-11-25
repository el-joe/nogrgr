<?php

namespace App\Modules\Integrations\Enums;

use App\Modules\Integrations\DTOs\SettingField;
use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Central payment gateways at launch (docs/plan/01-modules.md: COD, Stripe, PayPal,
 * Paymob, Tap, MyFatoorah, Moyasar, Tabby, Tamara). Each declares its credential
 * fields; the remaining old drivers join in P9 behind the same contract.
 */
enum PaymentGatewayCode: string
{
    use HasEnumHelpers;

    case Cod = 'cod';
    case Stripe = 'stripe';
    case Paypal = 'paypal';
    case Paymob = 'paymob';
    case Tap = 'tap';
    case Myfatoorah = 'myfatoorah';
    case Moyasar = 'moyasar';
    case Tabby = 'tabby';
    case Tamara = 'tamara';

    /**
     * Brand name (not translated, like the old system's gateway names).
     */
    public function brand(): string
    {
        return match ($this) {
            self::Cod => 'Cash on delivery',
            self::Stripe => 'Stripe',
            self::Paypal => 'PayPal',
            self::Paymob => 'Paymob',
            self::Tap => 'Tap',
            self::Myfatoorah => 'MyFatoorah',
            self::Moyasar => 'Moyasar',
            self::Tabby => 'Tabby',
            self::Tamara => 'Tamara',
        };
    }

    public function initials(): string
    {
        return match ($this) {
            self::Cod => 'COD',
            self::Stripe => 'ST',
            self::Paypal => 'PP',
            self::Paymob => 'PM',
            self::Tap => 'TP',
            self::Myfatoorah => 'MF',
            self::Moyasar => 'MY',
            self::Tabby => 'TB',
            self::Tamara => 'TM',
        };
    }

    /**
     * Payment methods shown under the gateway name (English, as at checkout).
     */
    public function methods(): string
    {
        return match ($this) {
            self::Cod => 'Cash',
            self::Stripe => 'Cards, Apple Pay, Google Pay',
            self::Paypal => 'PayPal balance, Cards',
            self::Paymob => 'Cards, Wallets',
            self::Tap => 'Cards, mada, Apple Pay',
            self::Myfatoorah => 'Cards, KNET, mada',
            self::Moyasar => 'Cards, mada, Apple Pay',
            self::Tabby => 'Buy now, pay later',
            self::Tamara => 'Buy now, pay later',
        };
    }

    /**
     * Cash on delivery has no external account, so nothing to connect or test.
     */
    public function isOffline(): bool
    {
        return $this === self::Cod;
    }

    /**
     * @return list<SettingField>
     */
    public function credentialFields(): array
    {
        $secret = SettingFieldType::Secret;

        return match ($this) {
            self::Cod => [],
            self::Stripe => [
                new SettingField('publishable_key', required: true),
                new SettingField('secret_key', $secret, true),
                new SettingField('webhook_secret', $secret),
            ],
            self::Paypal => [
                new SettingField('client_id', required: true),
                new SettingField('client_secret', $secret, true),
                new SettingField('webhook_id'),
            ],
            self::Paymob => [
                new SettingField('api_key', $secret, true, maxLength: 1000),
                new SettingField('integration_id', required: true, maxLength: 32),
                new SettingField('iframe_id', maxLength: 32),
                new SettingField('hmac_secret', $secret, true),
            ],
            self::Tap => [
                new SettingField('public_key', required: true),
                new SettingField('secret_key', $secret, true),
            ],
            self::Myfatoorah => [
                new SettingField('api_key', $secret, true, maxLength: 2000),
                new SettingField('webhook_secret', $secret),
            ],
            self::Moyasar => [
                new SettingField('publishable_key', required: true),
                new SettingField('secret_key', $secret, true),
            ],
            self::Tabby => [
                new SettingField('public_key', required: true),
                new SettingField('secret_key', $secret, true),
                new SettingField('merchant_code', required: true, maxLength: 64),
            ],
            self::Tamara => [
                new SettingField('api_token', $secret, true, maxLength: 2000),
                new SettingField('notification_token', $secret),
                new SettingField('public_key'),
            ],
        };
    }
}
