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
    case Careers = 'careers';
    case Press = 'press';
    case IntellectualProperty = 'intellectual-property-policy';
    case ReportActivity = 'report-suspicious-activity';
    case SafetyCenter = 'safety-center';
    case PurchaseProtection = 'purchase-protection';
    case Partner = 'partner-with-us';
    case PrivacyChoices = 'privacy-choices';

    /**
     * Footer column of the page link (Figma Ecommet footer).
     */
    public function footerGroup(): string
    {
        return match ($this) {
            self::About, self::Contact, self::Careers, self::Press => 'company',
            self::Shipping, self::Returns, self::IntellectualProperty, self::ReportActivity => 'service',
            self::Faq, self::SafetyCenter, self::PurchaseProtection, self::Partner => 'help',
            self::Terms, self::Privacy, self::PrivacyChoices => 'legal',
        };
    }

    /**
     * Pages of the original footers (Elora / Souqify); the Ecommet footer (Figma 1:1162) lists all of them.
     */
    public function inClassicFooter(): bool
    {
        return in_array($this, [self::About, self::Contact, self::Terms, self::Privacy, self::Returns, self::Shipping, self::Faq], true);
    }

    /**
     * Position inside the Ecommet footer column / legal bar (Figma order).
     */
    public function ecommetRank(): int
    {
        return array_search($this, [
            self::About, self::Contact, self::Careers, self::Press,
            self::Returns, self::IntellectualProperty, self::Shipping, self::ReportActivity,
            self::SafetyCenter, self::PurchaseProtection, self::Partner,
            self::Terms, self::Privacy, self::PrivacyChoices, self::Faq,
        ], true);
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
