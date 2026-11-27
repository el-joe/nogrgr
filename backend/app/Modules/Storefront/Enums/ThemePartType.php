<?php

namespace App\Modules\Storefront\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Template part types the storefront shell actually renders (ADM-088, decision #120).
 * Each type declares its settings fields; the admin form renders them and the request
 * validates them from this declaration. The theme's default + active part of a type is
 * applied on every page of that theme (`x-sf::layout`):
 *
 *   announcement  bar above the store bar: message (per locale), optional link, tone
 *   header        sticky header on scroll
 *   footer        copyright line used when the store has not written its own (per locale, :year)
 *   product_card  star rating and second (hover) image on product cards on / off
 *
 * Field kinds: text (translatable, per locale), link (store path or https URL), select
 * (options), toggle (bool).
 */
enum ThemePartType: string
{
    use HasEnumHelpers;

    case Announcement = 'announcement';
    case Header = 'header';
    case Footer = 'footer';
    case ProductCard = 'product_card';

    /**
     * @return list<array{name: string, kind: 'text'|'link'|'select'|'toggle', required?: bool, max?: int, options?: list<string>, default?: mixed}>
     */
    public function fields(): array
    {
        return match ($this) {
            self::Announcement => [
                ['name' => 'message', 'kind' => 'text', 'required' => true, 'max' => 160],
                ['name' => 'link', 'kind' => 'link', 'max' => 255],
                ['name' => 'tone', 'kind' => 'select', 'options' => ['dark', 'brand', 'light'], 'default' => 'dark'],
            ],
            self::Header => [
                ['name' => 'sticky', 'kind' => 'toggle', 'default' => false],
            ],
            self::Footer => [
                ['name' => 'copyright', 'kind' => 'text', 'required' => true, 'max' => 200],
            ],
            self::ProductCard => [
                ['name' => 'show_rating', 'kind' => 'toggle', 'default' => true],
                ['name' => 'show_hover_image', 'kind' => 'toggle', 'default' => true],
            ],
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Announcement => 'megaphone',
            self::Header => 'panel-left',
            self::Footer => 'layers',
            self::ProductCard => 'box',
        };
    }

    public static function translationKey(): string
    {
        return 'content::enums.theme_part_type';
    }
}
