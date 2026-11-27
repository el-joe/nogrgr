<?php

namespace App\Modules\Storefront\Services\Catalog;

use App\Modules\Commerce\Services\Payments\StorefrontGatewayService;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Storefront\Services\Appearance\StorefrontAppearance;
use App\Modules\Storefront\Services\Pages\StorePageReader;
use Illuminate\Support\Facades\Route;

/**
 * Footer data of the store (Ecommet 1:573): link columns from the store pages (sf-data's
 * StorePageReader), the store's own footer links (VEN-034e) + catalog / account links, and the "We accept" badges of the
 * payment methods checkout actually offers (StorefrontGatewayService::options(), RQA-SF-002).
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

        // The store's own footer links (Appearance › Footer, VEN-034e) after its pages.
        foreach (app(StorefrontAppearance::class)->footerLinks() as $column => $links) {
            foreach ($links as $link) {
                $columns[$column][] = ['title' => $link['title'], 'url' => $link['url']];
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

        // Exactly the methods checkout lists (centrally usable + connected keys; COD is never offered there).
        foreach (app(StorefrontGatewayService::class)->options() as $option) {
            $badges = [...$badges, ...match ($option->code) {
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
