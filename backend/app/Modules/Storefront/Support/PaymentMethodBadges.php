<?php

namespace App\Modules\Storefront\Support;

use Illuminate\Support\Str;

/**
 * Payment method chips under each gateway (spec 08 §1, old PaymentMethodIcons): driver
 * method keys → English label + mini icon key. Labels stay English in both locales,
 * like the old project; unknown keys fall back to a capitalised label + generic icon.
 */
final class PaymentMethodBadges
{
    /**
     * @var array<string, array{label: string, icon: string}>
     */
    private const MAP = [
        'card' => ['label' => 'Card', 'icon' => 'card'],
        'visa' => ['label' => 'Visa', 'icon' => 'visa'],
        'mastercard' => ['label' => 'Mastercard', 'icon' => 'mastercard'],
        'amex' => ['label' => 'Amex', 'icon' => 'amex'],
        'apple_pay' => ['label' => 'Apple Pay', 'icon' => 'apple_pay'],
        'google_pay' => ['label' => 'Google Pay', 'icon' => 'google_pay'],
        'mada' => ['label' => 'Mada', 'icon' => 'mada'],
        'stc_pay' => ['label' => 'STC Pay', 'icon' => 'stc_pay'],
        'knet' => ['label' => 'KNET', 'icon' => 'knet'],
        'paypal' => ['label' => 'PayPal', 'icon' => 'paypal'],
        'paypal_balance' => ['label' => 'PayPal', 'icon' => 'paypal'],
        'wallet' => ['label' => 'Wallet', 'icon' => 'wallet'],
        'wallets' => ['label' => 'Wallets', 'icon' => 'wallet'],
        'ewallet' => ['label' => 'eWallet', 'icon' => 'wallet'],
        'bank_transfer' => ['label' => 'Bank Transfer', 'icon' => 'bank'],
        'klarna' => ['label' => 'Klarna', 'icon' => 'klarna'],
        'bnpl' => ['label' => 'Buy Now Pay Later', 'icon' => 'bnpl'],
    ];

    /**
     * @param  iterable<string>  $methods
     * @return list<array{key: string, label: string, icon: string}>
     */
    public static function resolve(iterable $methods): array
    {
        $badges = [];
        $seen = [];

        foreach ($methods as $method) {
            $entry = self::MAP[$method] ?? ['label' => Str::ucfirst(str_replace('_', ' ', $method)), 'icon' => 'generic'];

            if (isset($seen[$entry['label']])) {
                continue;
            }

            $seen[$entry['label']] = true;
            $badges[] = ['key' => $method] + $entry;
        }

        return $badges;
    }
}
