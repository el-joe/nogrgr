<?php

namespace App\Modules\Storefront\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * The Twig templates a custom theme package must ship (`templates/<value>.twig`) and the
 * storefront view each one replaces (SF-ALL-006). Pages not listed here — checkout,
 * payment, sign-in / password, order detail / tracking / invoice, returns, support —
 * always render with the store's built-in theme (security rule, decision #121).
 */
enum CustomThemeTemplate: string
{
    use HasEnumHelpers;

    case Layout = 'layout';
    case Home = 'home';
    case Listing = 'listing';
    case Categories = 'categories';
    case Category = 'category';
    case Product = 'product';
    case Search = 'search';
    case Cart = 'cart';
    case Account = 'account';
    case Page = 'page';
    case NotFound = '404';
    case ServerError = '500';

    public static function translationKey(): string
    {
        return 'content::custom_themes.enums.template';
    }

    public function file(): string
    {
        return 'templates/'.$this->value.'.twig';
    }

    /**
     * Storefront Blade view names (StorefrontView) this template replaces. The shared
     * `pages.listing` view picks listing / category / search from its data.
     *
     * @return list<string>
     */
    public function views(): array
    {
        return match ($this) {
            self::Layout => [],
            self::Home => ['pages.home'],
            self::Listing, self::Category, self::Search => ['pages.listing'],
            self::Categories => ['pages.categories'],
            self::Product => ['pages.product'],
            self::Cart => ['pages.cart'],
            self::Account => ['pages.orders.index', 'pages.favorites.index'],
            self::Page => ['pages.page'],
            self::NotFound => ['pages.errors.404'],
            self::ServerError => ['pages.errors.500'],
        };
    }

    /**
     * Templates that render a full page (every one except the shared layout).
     *
     * @return list<self>
     */
    public static function pages(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $template): bool => $template !== self::Layout));
    }
}
