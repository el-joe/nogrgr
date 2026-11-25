<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Product fields a vendor may ask to override for its own store (edit requests).
 * Text fields are per locale; `images` is a list of image paths.
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

    public function isTranslatable(): bool
    {
        return $this !== self::Images;
    }

    /**
     * Fields that make sense on a single variant (variant title / image).
     *
     * @return list<self>
     */
    public static function forVariant(): array
    {
        return [self::Name, self::Images];
    }
}
