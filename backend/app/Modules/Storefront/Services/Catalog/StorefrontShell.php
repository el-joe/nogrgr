<?php

namespace App\Modules\Storefront\Services\Catalog;

use App\Modules\Commerce\Models\StorePaymentGateway;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Storefront\Services\Pages\StorePageReader;
use Illuminate\Support\Facades\Route;

/**
 * Footer data of the store (Ecommet 1:573): link columns from the store pages (sf-data's
 * StorePageReader) + catalog / account links, and the "We accept" badges of the store's
 * active payment methods.
 */
class StorefrontShell
{
    /**
     * @return array{columns: array<string, list<array{title: string, url: string}>>, legal: list<array{title: string, url: string}>}
     */
    public function footerLinks(): array
    {
        $columns = ['company' => [], 'service' => [], 'help' => []];
        $legal = [];

        if (class_exists(StorePageReader::class) && Route::has('storefront.pages.show')) {
            foreach (app(StorePageReader::class)->footerLinks() as $link) {
                if ($link['group'] === 'legal') {
                    $legal[] = ['title' => $link['title'], 'url' => $link['url']];
                } else {
                    $columns[$link['group']][] = ['title' => $link['title'], 'url' => $link['url']];
                }
            }
        }

        foreach ([
            'storefront.orders.index' => 'shell.footer.track_order',
            'storefront.categories.index' => 'shell.footer.all_categories',
        ] as $route => $label) {
            if (Route::has($route)) {
                $columns['help'][] = ['title' => __st($label), 'url' => route($route)];
            }
        }

        return ['columns' => array_filter($columns), 'legal' => $legal];
    }

    /**
     * @return list<string> badge keys: visa, mastercard, applepay, paypal, cod, tabby, tamara, mada…
     */
    public function paymentBadges(): array
    {
        $badges = [];

        foreach (StorePaymentGateway::query()->where('is_active', true)->where('is_hidden', false)->get() as $gateway) {
            $badges = [...$badges, ...match ($gateway->code) {
                PaymentGatewayCode::Stripe, PaymentGatewayCode::Paymob, PaymentGatewayCode::Tap, PaymentGatewayCode::Moyasar, PaymentGatewayCode::Myfatoorah => ['visa', 'mastercard'],
                PaymentGatewayCode::Paypal => ['paypal'],
                PaymentGatewayCode::Cod => ['cod'],
                PaymentGatewayCode::Tabby => ['tabby'],
                PaymentGatewayCode::Tamara => ['tamara'],
            }];
        }

        return array_values(array_unique($badges));
    }
}
