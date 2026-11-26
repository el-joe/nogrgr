<?php

namespace App\Modules\Storefront\Enums;

/**
 * The CMS pages every store starts with (SF-ECO-034). Default titles / bodies come from
 * the `pages` static-text group (pages.system.<slug>.title|body) in ar and en.
 */
enum SystemStorePage: string
{
    case About = 'about';
    case Contact = 'contact';
    case Terms = 'terms';
    case Privacy = 'privacy';
    case Returns = 'returns-policy';
    case Shipping = 'shipping-info';
    case Faq = 'faq';

    /**
     * Footer column of the page link (Figma Ecommet footer).
     */
    public function footerGroup(): string
    {
        return match ($this) {
            self::About, self::Contact => 'company',
            self::Shipping, self::Returns => 'service',
            self::Faq => 'help',
            self::Terms, self::Privacy => 'legal',
        };
    }

    public function sortOrder(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }

    public function textKey(): string
    {
        return str_replace('-', '_', $this->value);
    }
}
