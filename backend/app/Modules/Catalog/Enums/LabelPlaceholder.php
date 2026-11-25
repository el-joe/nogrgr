<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Placeholders a product label text may contain, e.g. "Delivered by {delivery_date}".
 * Resolved per shopper on the storefront; the admin preview uses sample values.
 */
enum LabelPlaceholder: string
{
    use HasEnumHelpers;

    case DeliveryDate = 'delivery_date';
    case Stock = 'stock';
    case FreeShippingMin = 'free_shipping_min';

    public function token(): string
    {
        return '{'.$this->value.'}';
    }

    /**
     * Sample value shown in the admin preview.
     */
    public function sample(): string
    {
        return match ($this) {
            self::DeliveryDate => now()->addDays(3)->locale(app()->getLocale())->translatedFormat('D j M'),
            self::Stock => '4',
            self::FreeShippingMin => '$50.00',
        };
    }

    /**
     * @return list<string>
     */
    public static function tokens(): array
    {
        return array_map(static fn (self $placeholder): string => $placeholder->token(), self::cases());
    }

    /**
     * Placeholder => sample value, for the live preview.
     *
     * @return array<string, string>
     */
    public static function samples(): array
    {
        $samples = [];

        foreach (self::cases() as $placeholder) {
            $samples[$placeholder->token()] = $placeholder->sample();
        }

        return $samples;
    }
}
