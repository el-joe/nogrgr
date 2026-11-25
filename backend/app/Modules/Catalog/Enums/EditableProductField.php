<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Product fields a vendor may ask to override for its own store (edit requests).
 * Text fields are per locale; `images` is a list of image paths; `weight_grams` is a number.
 */
enum EditableProductField: string
{
    use HasEnumHelpers;

    case Name = 'name';
    case Summary = 'summary';
    case Description = 'description';
    case MetaTitle = 'meta_title';
    case MetaDescription = 'meta_description';
    case Images = 'images';
    case Weight = 'weight_grams';

    public function isTranslatable(): bool
    {
        return $this !== self::Images && $this !== self::Weight;
    }

    /**
     * Numeric fields (one value, no locale).
     */
    public function isNumeric(): bool
    {
        return $this === self::Weight;
    }

    /**
     * Fields that make sense on a single variant (variant title / image / weight).
     *
     * @return list<self>
     */
    public static function forVariant(): array
    {
        return [self::Name, self::Images, self::Weight];
    }
}
