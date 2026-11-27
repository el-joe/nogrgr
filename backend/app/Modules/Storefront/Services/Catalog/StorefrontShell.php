<?php

namespace App\Modules\Storefront\Services\Catalog;

use App\Modules\Commerce\Services\Payments\StorefrontGatewayService;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Storefront\Enums\SystemStorePage;
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
     * @param  bool  $ecommet  the Ecommet footer (Figma 1:1162): every system page in the Figma order, plus the
     *                         sitemap and support links, FAQ in the legal bar, and no catalog / account extras
     * @return array{columns: array<string, list<array{title: string, url: string}>>, legal: list<array{title: string, url: string}>}
     */
    public function footerLinks(bool $ecommet = false): array
    {
        $columns = ['company' => [], 'service' => [], 'help' => []];
        $legal = [];
        $ranked = ['company' => [], 'service' => [], 'help' => [], 'legal' => []];

        if (class_exists(StorePageReader::class) && Route::has('storefront.pages.show')) {
            foreach (app(StorePageReader::class)->footerLinks() as $link) {
                $page = SystemStorePage::tryFrom($link['slug']);

                if (! $ecommet && $page !== null && ! $page->inClassicFooter()) {
                    continue;
                }

                $group = $ecommet && $page === SystemStorePage::Faq ? 'legal' : $link['group'];
                $rank = $ecommet && $page !== null ? $page->ecommetRank() : 100;
                $ranked[$group][] = ['rank' => $rank, 'title' => $link['title'], 'url' => $link['url']];
            }
        }

        if ($ecommet) {
            $contact = Route::has('storefront.pages.show') ? route('storefront.pages.show', SystemStorePage::Contact->value) : null;
            $support = Route::has('storefront.support.index') ? route('storefront.support.index') : $contact;

            if (Route::has('storefront.sitemap')) {
                $ranked['help'][] = ['rank' => SystemStorePage::PurchaseProtection->ecommetRank() + 0.5, 'title' => __st('shell.footer.sitemap'), 'url' => route('storefront.sitemap')];
            }

            if ($support !== null) {
                $ranked['legal'][] = ['rank' => SystemStorePage::PrivacyChoices->ecommetRank() + 0.5, 'title' => __st('shell.footer.support'), 'url' => $support];
            }
        }

        foreach ($ranked as $group => $links) {
            usort($links, static fn (array $a, array $b): int => $a['rank'] <=> $b['rank']);
            $items = array_map(static fn (array $link): array => ['title' => $link['title'], 'url' => $link['url']], $links);

            if ($group === 'legal') {
                $legal = $items;
            } else {
                $columns[$group] = $items;
            }
        }

        // The store's own footer links (Appearance › Footer, VEN-034e) after its pages.
        foreach (app(StorefrontAppearance::class)->footerLinks() as $column => $links) {
            foreach ($links as $link) {
                $columns[$column][] = ['title' => $link['title'], 'url' => $link['url']];
            }
        }

        if (! $ecommet) {
            foreach ([
                'storefront.orders.index' => 'shell.footer.track_order',
                'storefront.categories.index' => 'shell.footer.all_categories',
            ] as $route => $label) {
                if (Route::has($route)) {
                    $columns['help'][] = ['title' => __st($label), 'url' => route($route)];
                }
            }
        }

        return ['columns' => array_filter($columns), 'legal' => $legal];
    }

    /**
     * @param  bool  $wallets  also list Apple Pay for the gateways that offer it (Ecommet footer, Figma 1:610)
     * @return list<string> badge keys: visa, mastercard, applepay, paypal, cod, tabby, tamara, mada…
     */
    public function paymentBadges(bool $wallets = false): array
    {
        $badges = [];

        // Exactly the methods checkout lists (centrally usable + connected keys; COD is never offered there).
        foreach (app(StorefrontGatewayService::class)->options() as $option) {
            $badges = [...$badges, ...match ($option->code) {
                PaymentGatewayCode::Stripe, PaymentGatewayCode::Tap, PaymentGatewayCode::Moyasar => ['visa', 'mastercard', ...($wallets ? ['applepay'] : [])],
                PaymentGatewayCode::Paymob => ['visa', 'mastercard', 'fawry'],
                PaymentGatewayCode::Myfatoorah => ['visa', 'mastercard'],
                PaymentGatewayCode::Paypal => ['paypal'],
                PaymentGatewayCode::Cod => ['cod'],
                PaymentGatewayCode::Tabby => ['tabby'],
                PaymentGatewayCode::Tamara => ['tamara'],
            }];
        }

        return array_values(array_unique($badges));
    }
}
