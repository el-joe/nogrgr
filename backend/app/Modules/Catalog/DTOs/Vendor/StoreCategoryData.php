<?php

namespace App\Modules\Catalog\DTOs\Vendor;

use Illuminate\Http\UploadedFile;

/**
 * Store-own category create / edit (VEN-017b, decision #74).
 */
final readonly class StoreCategoryData
{
    /**
     * @param  array<string, string|null>  $name  locale => text (fallback locale required)
     * @param  array<string, string|null>  $description
     * @param  array<string, string|null>  $metaKeywords
     * @param  array<string, string|null>  $metaDescription
     * @param  list<int>|null  $countryIds  null = every selling country
     */
    public function __construct(
        public array $name,
        public ?string $slug,
        public ?int $parentId,
        public ?int $sortOrder,
        public array $description,
        public array $metaKeywords,
        public array $metaDescription,
        public ?array $countryIds,
        public bool $isActive,
        public bool $isFeatured,
        public ?UploadedFile $image,
        public bool $removeImage,
    ) {}
}
